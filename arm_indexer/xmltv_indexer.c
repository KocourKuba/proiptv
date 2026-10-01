/*
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

/*
 * xmltv_indexer - command line XMLTV indexer for the ProIPTV plugin.
 *
 * Builds exactly the index Epg_Manager_Xmltv::reindex_xmltv() builds, into the same sqlite
 * schema, and can read a channel's programmes back out of it. It exists because the plugin runs
 * on a 32-bit php whose file offsets stop at 2Gb: php cannot fseek() past that, so a source
 * larger than 2Gb is partly unreachable no matter how it is indexed. This is built with
 * _FILE_OFFSET_BITS=64, so the same algorithm addresses the whole file.
 *
 * Built by arm_indexer/build.sh, which installs it as dune_plugin/bin/xmltv_indexer.
 *
 *   xmltv_indexer index   <xmltv_file> <db_file>
 *   xmltv_indexer extract <xmltv_file> <db_file> <channel_id>
 *   xmltv_indexer range   <xmltv_file> <start> <length>
 *   xmltv_indexer blocks  <db_file> <channel_id>
 *
 * 'index' writes epg_channels, epg_picons, epg_entries and epg_stat.
 * 'extract' writes the programme bytes of one channel to stdout, in file order, so the caller
 * can wrap them in <tv>..</tv> and parse them - that is the path php uses for offsets it cannot
 * reach itself.
 * 'blocks' prints the indexed byte ranges of one channel, for diagnostics.
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>
#include <math.h>
#include <time.h>
#include <sys/time.h>
#include "sqlite3.h"

#define CHANNELS_BLOCK_SIZE   65536
#define INDEX_BLOCK_SIZE      65536
#define MAX_CHANNEL_ELEMENT   1048576
#define EXTRACT_CHUNK         (256 * 1024)

/* ------------------------------------------------------------------ md5 */
/* public domain implementation, only used to hash picon urls so the value
 * matches php's md5() for the epg_picons primary key */
typedef struct {
    unsigned int state[4];
    unsigned int count[2];
    unsigned char buffer[64];
} md5_ctx;

#define F(x, y, z) (((x) & (y)) | (~(x) & (z)))
#define G(x, y, z) (((x) & (z)) | ((y) & ~(z)))
#define H(x, y, z) ((x) ^ (y) ^ (z))
#define I(x, y, z) ((y) ^ ((x) | ~(z)))
#define ROTL(x, n) (((x) << (n)) | ((x) >> (32 - (n))))
#define STEP(f, a, b, c, d, x, t, s) \
    (a) += f((b), (c), (d)) + (x) + (t); (a) = ROTL((a), (s)); (a) += (b);

static void md5_transform(md5_ctx *ctx, const unsigned char *block)
{
    unsigned int a = ctx->state[0], b = ctx->state[1], c = ctx->state[2], d = ctx->state[3];
    unsigned int x[16];
    int i;
    for (i = 0; i < 16; i++) {
        x[i] = (unsigned int)block[i * 4] | ((unsigned int)block[i * 4 + 1] << 8)
             | ((unsigned int)block[i * 4 + 2] << 16) | ((unsigned int)block[i * 4 + 3] << 24);
    }
    STEP(F, a, b, c, d, x[0], 0xd76aa478, 7)   STEP(F, d, a, b, c, x[1], 0xe8c7b756, 12)
    STEP(F, c, d, a, b, x[2], 0x242070db, 17)  STEP(F, b, c, d, a, x[3], 0xc1bdceee, 22)
    STEP(F, a, b, c, d, x[4], 0xf57c0faf, 7)   STEP(F, d, a, b, c, x[5], 0x4787c62a, 12)
    STEP(F, c, d, a, b, x[6], 0xa8304613, 17)  STEP(F, b, c, d, a, x[7], 0xfd469501, 22)
    STEP(F, a, b, c, d, x[8], 0x698098d8, 7)   STEP(F, d, a, b, c, x[9], 0x8b44f7af, 12)
    STEP(F, c, d, a, b, x[10], 0xffff5bb1, 17) STEP(F, b, c, d, a, x[11], 0x895cd7be, 22)
    STEP(F, a, b, c, d, x[12], 0x6b901122, 7)  STEP(F, d, a, b, c, x[13], 0xfd987193, 12)
    STEP(F, c, d, a, b, x[14], 0xa679438e, 17) STEP(F, b, c, d, a, x[15], 0x49b40821, 22)
    STEP(G, a, b, c, d, x[1], 0xf61e2562, 5)   STEP(G, d, a, b, c, x[6], 0xc040b340, 9)
    STEP(G, c, d, a, b, x[11], 0x265e5a51, 14) STEP(G, b, c, d, a, x[0], 0xe9b6c7aa, 20)
    STEP(G, a, b, c, d, x[5], 0xd62f105d, 5)   STEP(G, d, a, b, c, x[10], 0x02441453, 9)
    STEP(G, c, d, a, b, x[15], 0xd8a1e681, 14) STEP(G, b, c, d, a, x[4], 0xe7d3fbc8, 20)
    STEP(G, a, b, c, d, x[9], 0x21e1cde6, 5)   STEP(G, d, a, b, c, x[14], 0xc33707d6, 9)
    STEP(G, c, d, a, b, x[3], 0xf4d50d87, 14)  STEP(G, b, c, d, a, x[8], 0x455a14ed, 20)
    STEP(G, a, b, c, d, x[13], 0xa9e3e905, 5)  STEP(G, d, a, b, c, x[2], 0xfcefa3f8, 9)
    STEP(G, c, d, a, b, x[7], 0x676f02d9, 14)  STEP(G, b, c, d, a, x[12], 0x8d2a4c8a, 20)
    STEP(H, a, b, c, d, x[5], 0xfffa3942, 4)   STEP(H, d, a, b, c, x[8], 0x8771f681, 11)
    STEP(H, c, d, a, b, x[11], 0x6d9d6122, 16) STEP(H, b, c, d, a, x[14], 0xfde5380c, 23)
    STEP(H, a, b, c, d, x[1], 0xa4beea44, 4)   STEP(H, d, a, b, c, x[4], 0x4bdecfa9, 11)
    STEP(H, c, d, a, b, x[7], 0xf6bb4b60, 16)  STEP(H, b, c, d, a, x[10], 0xbebfbc70, 23)
    STEP(H, a, b, c, d, x[13], 0x289b7ec6, 4)  STEP(H, d, a, b, c, x[0], 0xeaa127fa, 11)
    STEP(H, c, d, a, b, x[3], 0xd4ef3085, 16)  STEP(H, b, c, d, a, x[6], 0x04881d05, 23)
    STEP(H, a, b, c, d, x[9], 0xd9d4d039, 4)   STEP(H, d, a, b, c, x[12], 0xe6db99e5, 11)
    STEP(H, c, d, a, b, x[15], 0x1fa27cf8, 16) STEP(H, b, c, d, a, x[2], 0xc4ac5665, 23)
    STEP(I, a, b, c, d, x[0], 0xf4292244, 6)   STEP(I, d, a, b, c, x[7], 0x432aff97, 10)
    STEP(I, c, d, a, b, x[14], 0xab9423a7, 15) STEP(I, b, c, d, a, x[5], 0xfc93a039, 21)
    STEP(I, a, b, c, d, x[12], 0x655b59c3, 6)  STEP(I, d, a, b, c, x[3], 0x8f0ccc92, 10)
    STEP(I, c, d, a, b, x[10], 0xffeff47d, 15) STEP(I, b, c, d, a, x[1], 0x85845dd1, 21)
    STEP(I, a, b, c, d, x[8], 0x6fa87e4f, 6)   STEP(I, d, a, b, c, x[15], 0xfe2ce6e0, 10)
    STEP(I, c, d, a, b, x[6], 0xa3014314, 15)  STEP(I, b, c, d, a, x[13], 0x4e0811a1, 21)
    STEP(I, a, b, c, d, x[4], 0xf7537e82, 6)   STEP(I, d, a, b, c, x[11], 0xbd3af235, 10)
    STEP(I, c, d, a, b, x[2], 0x2ad7d2bb, 15)  STEP(I, b, c, d, a, x[9], 0xeb86d391, 21)
    ctx->state[0] += a; ctx->state[1] += b; ctx->state[2] += c; ctx->state[3] += d;
}

static void md5_init(md5_ctx *ctx)
{
    ctx->state[0] = 0x67452301; ctx->state[1] = 0xefcdab89;
    ctx->state[2] = 0x98badcfe; ctx->state[3] = 0x10325476;
    ctx->count[0] = ctx->count[1] = 0;
}

static void md5_update(md5_ctx *ctx, const unsigned char *data, size_t len)
{
    unsigned int idx = (ctx->count[0] >> 3) & 0x3F;
    size_t i, part;
    ctx->count[0] += (unsigned int)(len << 3);
    if (ctx->count[0] < (len << 3)) ctx->count[1]++;
    ctx->count[1] += (unsigned int)(len >> 29);
    part = 64 - idx;
    if (len >= part) {
        memcpy(&ctx->buffer[idx], data, part);
        md5_transform(ctx, ctx->buffer);
        for (i = part; i + 63 < len; i += 64) md5_transform(ctx, &data[i]);
        idx = 0;
    } else {
        i = 0;
    }
    memcpy(&ctx->buffer[idx], &data[i], len - i);
}

static void md5_hex(const char *s, char out[33])
{
    static const char hex[] = "0123456789abcdef";
    md5_ctx ctx;
    unsigned char bits[8], digest[16];
    unsigned int idx, padlen;
    static const unsigned char pad[64] = { 0x80 };
    int i;
    md5_init(&ctx);
    md5_update(&ctx, (const unsigned char *)s, strlen(s));
    for (i = 0; i < 2; i++) {
        bits[i * 4]     = (unsigned char)(ctx.count[i] & 0xFF);
        bits[i * 4 + 1] = (unsigned char)((ctx.count[i] >> 8) & 0xFF);
        bits[i * 4 + 2] = (unsigned char)((ctx.count[i] >> 16) & 0xFF);
        bits[i * 4 + 3] = (unsigned char)((ctx.count[i] >> 24) & 0xFF);
    }
    idx = (ctx.count[0] >> 3) & 0x3f;
    padlen = (idx < 56) ? (56 - idx) : (120 - idx);
    md5_update(&ctx, pad, padlen);
    md5_update(&ctx, bits, 8);
    for (i = 0; i < 4; i++) {
        digest[i * 4]     = (unsigned char)(ctx.state[i] & 0xFF);
        digest[i * 4 + 1] = (unsigned char)((ctx.state[i] >> 8) & 0xFF);
        digest[i * 4 + 2] = (unsigned char)((ctx.state[i] >> 16) & 0xFF);
        digest[i * 4 + 3] = (unsigned char)((ctx.state[i] >> 24) & 0xFF);
    }
    for (i = 0; i < 16; i++) {
        out[i * 2]     = hex[digest[i] >> 4];
        out[i * 2 + 1] = hex[digest[i] & 0x0F];
    }
    out[32] = '\0';
}

/* ------------------------------------------------------- growable buffer */
typedef struct {
    char  *p;
    size_t len;
    size_t cap;
} buf;

static void buf_init(buf *b) { b->p = NULL; b->len = 0; b->cap = 0; }
static void buf_free(buf *b) { free(b->p); buf_init(b); }

static int buf_reserve(buf *b, size_t need)
{
    size_t cap;
    char *np;
    if (b->cap >= need) return 1;
    cap = b->cap ? b->cap : 1024;
    while (cap < need) cap *= 2;
    np = (char *)realloc(b->p, cap);
    if (np == NULL) return 0;
    b->p = np;
    b->cap = cap;
    return 1;
}

static int buf_add(buf *b, const char *s, size_t n)
{
    if (!buf_reserve(b, b->len + n + 1)) return 0;
    memcpy(b->p + b->len, s, n);
    b->len += n;
    b->p[b->len] = '\0';
    return 1;
}

static void buf_clear(buf *b) { b->len = 0; if (b->p) b->p[0] = '\0'; }

/* keep only the bytes from 'from' onwards */
static void buf_consume(buf *b, size_t from)
{
    if (from == 0) return;
    if (from >= b->len) { buf_clear(b); return; }
    memmove(b->p, b->p + from, b->len - from);
    b->len -= from;
    b->p[b->len] = '\0';
}

/* ------------------------------------------------------- utf8 lowercase */
/*
 * php's to_lower() is mb_convert_case(.., MB_CASE_LOWER, 'UTF-8'), so the alias has to be
 * lowercased by unicode rules rather than by the C locale. The ranges below are the ones that
 * occur in channel names: ascii, latin-1 supplement, latin extended-A/B, greek and cyrillic.
 * Anything else is left as it is, which matches mbstring for characters that have no lower
 * case form. validate_lowercase.php checks this against php over the real sources.
 */
static unsigned int cp_tolower(unsigned int c)
{
    if (c < 0x80) return (c >= 'A' && c <= 'Z') ? c + 32 : c;
    if (c >= 0xC0 && c <= 0xDE && c != 0xD7) return c + 32;          /* latin-1 supplement */
    /* 0130/0131 are the turkish dotted and dotless i and are NOT a case pair: the lower case of
     * I-with-dot is a plain 'i', and dotless i is already lower case. Channel names do carry them
     * (SINEMA AILE HD [TR] and friends), and treating them as a pair put the wrong alias in the
     * index, so they are handled before the even/odd pair rule around them. */
    if (c == 0x130) return 0x69;
    if (c == 0x131) return c;
    if (c >= 0x100 && c <= 0x137) return (c & 1) ? c : c + 1;        /* latin ext-A pairs */
    if (c >= 0x139 && c <= 0x148) return (c & 1) ? c + 1 : c;
    if (c >= 0x14A && c <= 0x177) return (c & 1) ? c : c + 1;
    if (c == 0x178) return 0xFF;
    if (c >= 0x179 && c <= 0x17E) return (c & 1) ? c + 1 : c;
    if (c >= 0x391 && c <= 0x3A9 && c != 0x3A2) return c + 32;       /* greek */
    if (c == 0x386) return 0x3AC;
    if (c >= 0x388 && c <= 0x38A) return c + 37;
    if (c == 0x38C) return 0x3CC;
    if (c == 0x38E) return 0x3CD;
    if (c == 0x38F) return 0x3CE;
    if (c >= 0x531 && c <= 0x556) return c + 48;                     /* armenian Ա-Ֆ */
    if (c >= 0x410 && c <= 0x42F) return c + 32;                     /* cyrillic А-Я */
    if (c >= 0x400 && c <= 0x40F) return c + 80;                     /* cyrillic Ѐ-Џ */
    if (c >= 0x460 && c <= 0x481) return (c & 1) ? c : c + 1;
    if (c >= 0x48A && c <= 0x4BF) return (c & 1) ? c : c + 1;
    if (c >= 0x4C1 && c <= 0x4CE) return (c & 1) ? c + 1 : c;
    if (c >= 0x4D0 && c <= 0x52F) return (c & 1) ? c : c + 1;
    return c;
}

static int utf8_decode(const char *s, size_t len, size_t *i, unsigned int *cp)
{
    unsigned char c = (unsigned char)s[*i];
    if (c < 0x80) { *cp = c; *i += 1; return 1; }
    if ((c & 0xE0) == 0xC0 && *i + 1 < len) {
        *cp = ((unsigned int)(c & 0x1F) << 6) | ((unsigned char)s[*i + 1] & 0x3F);
        *i += 2; return 2;
    }
    if ((c & 0xF0) == 0xE0 && *i + 2 < len) {
        *cp = ((unsigned int)(c & 0x0F) << 12) | (((unsigned char)s[*i + 1] & 0x3F) << 6)
            | ((unsigned char)s[*i + 2] & 0x3F);
        *i += 3; return 3;
    }
    if ((c & 0xF8) == 0xF0 && *i + 3 < len) {
        *cp = ((unsigned int)(c & 0x07) << 18) | (((unsigned char)s[*i + 1] & 0x3F) << 12)
            | (((unsigned char)s[*i + 2] & 0x3F) << 6) | ((unsigned char)s[*i + 3] & 0x3F);
        *i += 4; return 4;
    }
    *cp = c; *i += 1; return 1;    /* invalid sequence, pass the byte through */
}

static void utf8_encode(unsigned int cp, buf *out)
{
    char t[4];
    if (cp < 0x80) { t[0] = (char)cp; buf_add(out, t, 1); return; }
    if (cp < 0x800) {
        t[0] = (char)(0xC0 | (cp >> 6)); t[1] = (char)(0x80 | (cp & 0x3F));
        buf_add(out, t, 2); return;
    }
    if (cp < 0x10000) {
        t[0] = (char)(0xE0 | (cp >> 12)); t[1] = (char)(0x80 | ((cp >> 6) & 0x3F));
        t[2] = (char)(0x80 | (cp & 0x3F)); buf_add(out, t, 3); return;
    }
    t[0] = (char)(0xF0 | (cp >> 18)); t[1] = (char)(0x80 | ((cp >> 12) & 0x3F));
    t[2] = (char)(0x80 | ((cp >> 6) & 0x3F)); t[3] = (char)(0x80 | (cp & 0x3F));
    buf_add(out, t, 4);
}

static void to_lower_utf8(const char *s, size_t len, buf *out)
{
    size_t i = 0;
    unsigned int cp;
    buf_clear(out);
    while (i < len) {
        utf8_decode(s, len, &i, &cp);
        utf8_encode(cp_tolower(cp), out);
    }
}

/* --------------------------------------------------- xml entity decoding */
/*
 * libxml hands php the decoded text, so the same has to happen here. Only the five predefined
 * entities and numeric references are decoded - any other named entity is undefined without a
 * DTD and makes libxml reject the element, which is reported by returning 0 so the caller skips
 * the channel exactly as the php path does.
 */
static int xml_decode(const char *s, size_t len, buf *out)
{
    size_t i = 0;
    buf_clear(out);
    while (i < len) {
        if (s[i] != '&') {
            buf_add(out, s + i, 1);
            i++;
            continue;
        }
        if (len - i >= 5 && memcmp(s + i, "&amp;", 5) == 0)       { buf_add(out, "&", 1); i += 5; continue; }
        if (len - i >= 4 && memcmp(s + i, "&lt;", 4) == 0)        { buf_add(out, "<", 1); i += 4; continue; }
        if (len - i >= 4 && memcmp(s + i, "&gt;", 4) == 0)        { buf_add(out, ">", 1); i += 4; continue; }
        if (len - i >= 6 && memcmp(s + i, "&quot;", 6) == 0)      { buf_add(out, "\"", 1); i += 6; continue; }
        if (len - i >= 6 && memcmp(s + i, "&apos;", 6) == 0)      { buf_add(out, "'", 1); i += 6; continue; }
        if (len - i >= 4 && s[i + 1] == '#') {
            unsigned int cp = 0;
            size_t j = i + 2;
            int hex = 0;
            if (s[j] == 'x' || s[j] == 'X') { hex = 1; j++; }
            if (j >= len) return 0;
            for (; j < len && s[j] != ';'; j++) {
                int d;
                if (s[j] >= '0' && s[j] <= '9') d = s[j] - '0';
                else if (hex && s[j] >= 'a' && s[j] <= 'f') d = s[j] - 'a' + 10;
                else if (hex && s[j] >= 'A' && s[j] <= 'F') d = s[j] - 'A' + 10;
                else return 0;
                cp = cp * (hex ? 16 : 10) + (unsigned int)d;
                if (cp > 0x10FFFF) return 0;
            }
            if (j >= len) return 0;
            utf8_encode(cp, out);
            i = j + 1;
            continue;
        }
        return 0;   /* an entity libxml would need a DTD for */
    }
    return 1;
}

/* ------------------------------------------------------- small xml helpers */
/* find the value of attribute 'name' inside the open tag at [tag, tag_end) */
static int attr_value(const char *p, size_t len, const char *name, size_t *vs, size_t *vlen)
{
    size_t nl = strlen(name);
    size_t i = 0;
    while (i + nl + 2 < len) {
        if (memcmp(p + i, name, nl) == 0) {
            /* must be preceded by whitespace so 'id' does not match 'xid' */
            if (i == 0 || p[i - 1] == ' ' || p[i - 1] == '\t' || p[i - 1] == '\r' || p[i - 1] == '\n') {
                size_t j = i + nl;
                while (j < len && (p[j] == ' ' || p[j] == '\t' || p[j] == '\r' || p[j] == '\n')) j++;
                if (j < len && p[j] == '=') {
                    char q;
                    j++;
                    while (j < len && (p[j] == ' ' || p[j] == '\t' || p[j] == '\r' || p[j] == '\n')) j++;
                    if (j < len && (p[j] == '"' || p[j] == '\'')) {
                        q = p[j];
                        j++;
                        *vs = j;
                        while (j < len && p[j] != q) j++;
                        if (j <= len) { *vlen = j - *vs; return 1; }
                    }
                }
            }
        }
        i++;
    }
    return 0;
}

static int is_proto_http(const char *s, size_t len)
{
    return (len > 7 && strncmp(s, "http://", 7) == 0)
        || (len > 8 && strncmp(s, "https://", 8) == 0);
}

static const char *mem_find(const char *h, size_t hl, const char *n, size_t nl)
{
    if (nl == 0 || hl < nl) return NULL;
    {
        const char *end = h + hl - nl + 1;
        const char *p = h;
        while (p < end) {
            const char *q = (const char *)memchr(p, n[0], (size_t)(end - p));
            if (q == NULL) return NULL;
            if (memcmp(q, n, nl) == 0) return q;
            p = q + 1;
        }
    }
    return NULL;
}

static const char *mem_rfind(const char *h, size_t hl, const char *n, size_t nl)
{
    size_t i;
    if (nl == 0 || hl < nl) return NULL;
    for (i = hl - nl + 1; i > 0; i--) {
        if (memcmp(h + i - 1, n, nl) == 0) return h + i - 1;
    }
    return NULL;
}

/* -------------------------------------------------------------- database */
static const char *CREATE_CHANNELS =
    "CREATE TABLE epg_channels (alias TEXT PRIMARY KEY not null, channel_id TEXT not null, picon_hash TEXT);";
static const char *CREATE_PICONS =
    "CREATE TABLE epg_picons (picon_hash TEXT PRIMARY KEY not null, picon_url TEXT);";
static const char *CREATE_ENTRIES =
    "CREATE TABLE epg_entries (channel_id STRING not null, start INTEGER, end INTEGER);";
static const char *CREATE_ENTRIES_INDEX =
    "CREATE INDEX IF NOT EXISTS epg_entries_idx ON epg_entries (channel_id, start, end);";
static const char *CREATE_STAT =
    "CREATE TABLE IF NOT EXISTS epg_stat (name TEXT PRIMARY KEY, value REAL);";

static int db_exec(sqlite3 *db, const char *sql)
{
    char *err = NULL;
    if (sqlite3_exec(db, sql, NULL, NULL, &err) != SQLITE_OK) {
        fprintf(stderr, "sqlite: %s (%s)\n", err ? err : "?", sql);
        sqlite3_free(err);
        return 0;
    }
    return 1;
}

static void stat_set(sqlite3 *db, const char *name, double value)
{
    sqlite3_stmt *st = NULL;

    /* two decimals, the same precision Perf_Collector stores with (round($time, 2)), so a value
     * written here reads back like one written by the php indexer */
    value = (value < 0.0)
        ? -floor(-value * 100.0 + 0.5) / 100.0
        : floor(value * 100.0 + 0.5) / 100.0;

    if (sqlite3_prepare_v2(db, "INSERT OR REPLACE INTO epg_stat (name, value) VALUES (?, ?);", -1, &st, NULL) != SQLITE_OK) {
        return;
    }
    sqlite3_bind_text(st, 1, name, -1, SQLITE_STATIC);
    sqlite3_bind_double(st, 2, value);
    sqlite3_step(st);
    sqlite3_finalize(st);
}

/* wall clock, not clock(): the passes are dominated by reading the file, and cpu time would
 * under-report that in the numbers the plugin shows as indexing time */
static double now_sec(void)
{
    struct timeval tv;
    if (gettimeofday(&tv, NULL) != 0) {
        return (double)clock() / (double)CLOCKS_PER_SEC;
    }
    return (double)tv.tv_sec + (double)tv.tv_usec / 1000000.0;
}

/* ------------------------------------------------------- channels pass */
typedef struct {
    sqlite3      *db;
    sqlite3_stmt *alias;
    sqlite3_stmt *picon;
    buf           decoded;
    buf           lowered;
    long          indexed;
} chan_ctx;

/* one <channel>..</channel> element, already cut out of the file */
static void index_channel_element(chan_ctx *c, const char *el, size_t el_len)
{
    size_t tag_end_off, vs, vlen;
    const char *gt;
    char picon_hash[33];
    const char *p;
    size_t left;

    picon_hash[0] = '\0';

    gt = (const char *)memchr(el, '>', el_len);
    if (gt == NULL) return;
    tag_end_off = (size_t)(gt - el);

    /* id attribute of the open tag */
    if (!attr_value(el + 8, tag_end_off - 8, "id", &vs, &vlen)) return;
    if (vlen == 0) return;
    if (!xml_decode(el + 8 + vs, vlen, &c->decoded)) return;     /* libxml would reject it */
    if (c->decoded.len == 0) return;

    {
        /* keep the decoded id, the buffers get reused below */
        char *channel_id = (char *)malloc(c->decoded.len + 1);
        if (channel_id == NULL) return;
        memcpy(channel_id, c->decoded.p, c->decoded.len + 1);

        c->indexed++;

        /* first <icon src="http..."> wins, same as the php loop */
        p = el + tag_end_off;
        left = el_len - tag_end_off;
        while (1) {
            const char *icon = mem_find(p, left, "<icon", 5);
            const char *icon_gt;
            if (icon == NULL) break;
            icon_gt = (const char *)memchr(icon, '>', (size_t)(left - (size_t)(icon - p)));
            if (icon_gt == NULL) break;
            if (attr_value(icon + 5, (size_t)(icon_gt - icon) - 5, "src", &vs, &vlen) && vlen > 0) {
                if (xml_decode(icon + 5 + vs, vlen, &c->decoded) && c->decoded.len > 0
                    && is_proto_http(c->decoded.p, c->decoded.len)) {
                    md5_hex(c->decoded.p, picon_hash);
                    sqlite3_bind_text(c->picon, 1, picon_hash, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_text(c->picon, 2, c->decoded.p, (int)c->decoded.len, SQLITE_TRANSIENT);
                    sqlite3_step(c->picon);
                    sqlite3_reset(c->picon);
                    break;
                }
            }
            left -= (size_t)(icon_gt - p) + 1;
            p = icon_gt + 1;
        }

        /* alias for the id itself */
        to_lower_utf8(channel_id, strlen(channel_id), &c->lowered);
        sqlite3_bind_text(c->alias, 1, c->lowered.p ? c->lowered.p : "", (int)c->lowered.len, SQLITE_TRANSIENT);
        sqlite3_bind_text(c->alias, 2, channel_id, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(c->alias, 3, picon_hash, -1, SQLITE_TRANSIENT);
        sqlite3_step(c->alias);
        sqlite3_reset(c->alias);

        /*
         * One alias per <display-name>, including the empty ones: the php loop binds
         * to_lower($tag->nodeValue) with no emptiness check, so '<display-name></display-name>'
         * does add an alias of '' (sources do contain those), and leaving it out made the index
         * differ. A self-closing <display-name/> is an empty element too and has no closing tag
         * to search for.
         */
        p = el + tag_end_off;
        left = el_len - tag_end_off;
        while (1) {
            const char *dn = mem_find(p, left, "<display-name", 13);
            const char *dn_gt, *dn_close, *text;
            size_t text_len, advance;
            if (dn == NULL) break;
            dn_gt = (const char *)memchr(dn, '>', (size_t)(left - (size_t)(dn - p)));
            if (dn_gt == NULL) break;

            if (dn_gt > dn && *(dn_gt - 1) == '/') {
                text = dn_gt;                     /* <display-name/> - empty */
                text_len = 0;
                advance = (size_t)(dn_gt - p) + 1;
            } else {
                dn_close = mem_find(dn_gt, left - (size_t)(dn_gt - p), "</display-name>", 15);
                if (dn_close == NULL) break;
                text = dn_gt + 1;
                text_len = (size_t)(dn_close - dn_gt) - 1;
                advance = (size_t)(dn_close - p) + 15;
            }

            if (text_len == 0) {
                buf_clear(&c->lowered);
                sqlite3_bind_text(c->alias, 1, "", 0, SQLITE_STATIC);
                sqlite3_bind_text(c->alias, 2, channel_id, -1, SQLITE_TRANSIENT);
                sqlite3_bind_text(c->alias, 3, picon_hash, -1, SQLITE_TRANSIENT);
                sqlite3_step(c->alias);
                sqlite3_reset(c->alias);
            } else if (xml_decode(text, text_len, &c->decoded)) {
                to_lower_utf8(c->decoded.p ? c->decoded.p : "", c->decoded.len, &c->lowered);
                sqlite3_bind_text(c->alias, 1, c->lowered.p ? c->lowered.p : "", (int)c->lowered.len, SQLITE_TRANSIENT);
                sqlite3_bind_text(c->alias, 2, channel_id, -1, SQLITE_TRANSIENT);
                sqlite3_bind_text(c->alias, 3, picon_hash, -1, SQLITE_TRANSIENT);
                sqlite3_step(c->alias);
                sqlite3_reset(c->alias);
            }

            left -= advance;
            p += advance;
        }

        free(channel_id);
    }
}

static long index_channels(FILE *f, sqlite3 *db)
{
    chan_ctx c;
    buf b;
    char *chunk;
    int eof = 0;

    memset(&c, 0, sizeof(c));
    c.db = db;
    buf_init(&c.decoded);
    buf_init(&c.lowered);
    buf_init(&b);

    if (sqlite3_prepare_v2(db,
            "INSERT OR IGNORE INTO epg_channels (alias, channel_id, picon_hash) VALUES (?, ?, ?);",
            -1, &c.alias, NULL) != SQLITE_OK) return -1;
    if (sqlite3_prepare_v2(db,
            "INSERT OR REPLACE INTO epg_picons (picon_hash, picon_url) VALUES (?, ?);",
            -1, &c.picon, NULL) != SQLITE_OK) return -1;

    chunk = (char *)malloc(CHANNELS_BLOCK_SIZE);
    if (chunk == NULL) return -1;

    db_exec(db, "BEGIN;");
    rewind(f);

    while (1) {
        size_t got = 0;
        size_t consumed = 0;
        long pending_at = -1;

        if (!eof) {
            got = fread(chunk, 1, CHANNELS_BLOCK_SIZE, f);
            if (got == 0) eof = 1;
            else {
                if (!buf_add(&b, chunk, got)) break;
                if (got < CHANNELS_BLOCK_SIZE) eof = feof(f) ? 1 : 0;
            }
        }

        /* cut out every complete <channel ...> .. </channel> the buffer holds */
        while (1) {
            const char *at = mem_find(b.p + consumed, b.len - consumed, "<channel", 8);
            size_t at_off;
            char after;
            const char *close;
            if (at == NULL) break;
            at_off = (size_t)(at - b.p);
            if (at_off + 8 >= b.len) { pending_at = (long)at_off; break; }
            after = b.p[at_off + 8];
            if (after != ' ' && after != '\t' && after != '\r' && after != '\n' && after != '>') {
                consumed = at_off + 8;
                continue;
            }
            close = mem_find(b.p + at_off + 8, b.len - at_off - 8, "</channel>", 10);
            if (close == NULL) { pending_at = (long)at_off; break; }
            index_channel_element(&c, b.p + at_off, (size_t)(close - (b.p + at_off)) + 10);
            consumed = (size_t)(close - b.p) + 10;
        }

        if (pending_at < 0) {
            size_t keep = b.len > 8 ? b.len - 8 : 0;
            buf_consume(&b, keep > consumed ? keep : consumed);
        } else if (b.len - (size_t)pending_at > MAX_CHANNEL_ELEMENT) {
            fprintf(stderr, "unterminated <channel> element, skipped\n");
            buf_consume(&b, (size_t)pending_at + 8);
        } else {
            buf_consume(&b, (size_t)pending_at);
        }

        if (eof) break;
    }

    db_exec(db, "COMMIT;");
    sqlite3_finalize(c.alias);
    sqlite3_finalize(c.picon);
    buf_free(&c.decoded);
    buf_free(&c.lowered);
    buf_free(&b);
    free(chunk);
    return c.indexed;
}

/* -------------------------------------------------------- entries pass */
static long index_entries(FILE *f, sqlite3 *db)
{
    sqlite3_stmt *st = NULL;
    char *block;
    long long file_size, block_pos = 0;
    char *prev_channel = NULL;
    long long start_program_block = 0;
    long rows = 0;

    if (sqlite3_prepare_v2(db,
            "INSERT INTO epg_entries (channel_id, start, end) VALUES (?, ?, ?);",
            -1, &st, NULL) != SQLITE_OK) return -1;

    if (fseeko(f, 0, SEEK_END) != 0) return -1;
    file_size = (long long)ftello(f);
    if (file_size < 0) return -1;

    block = (char *)malloc(INDEX_BLOCK_SIZE);
    if (block == NULL) return -1;

    db_exec(db, "BEGIN;");

    while (block_pos < file_size) {
        size_t block_len, limit, pos = 0;
        long long next_block_pos;
        int last_block;

        if (fseeko(f, (off_t)block_pos, SEEK_SET) != 0) break;
        block_len = fread(block, 1, INDEX_BLOCK_SIZE, f);
        if (block_len == 0) break;

        last_block = (block_pos + (long long)block_len >= file_size);
        limit = block_len;
        next_block_pos = block_pos + (long long)block_len;

        if (!last_block) {
            const char *last_open = mem_rfind(block, block_len, "<programme", 10);
            if (last_open == NULL) { block_pos += (long long)block_len - 9; continue; }
            if (last_open == block) {
                next_block_pos = block_pos + (long long)block_len - 9;
            } else {
                limit = (size_t)(last_open - block);
                next_block_pos = block_pos + (long long)limit;
            }
        }

        while (pos < limit) {
            const char *tag = mem_find(block + pos, limit - pos, "<programme", 10);
            size_t tag_off, ch_off, ch_end;
            const char *ch;
            if (tag == NULL) break;
            tag_off = (size_t)(tag - block);

            ch = mem_find(block + tag_off, block_len - tag_off, "channel=\"", 9);
            if (ch == NULL) break;
            ch_off = (size_t)(ch - block) + 9;

            /* the attribute has to belong to this open tag */
            {
                const char *gt = (const char *)memchr(block + tag_off, '>', block_len - tag_off);
                if (gt != NULL && (size_t)(gt - block) < ch_off) { pos = tag_off + 10; continue; }
            }

            {
                const char *q = (const char *)memchr(block + ch_off, '"', block_len - ch_off);
                if (q == NULL) break;
                ch_end = (size_t)(q - block);
            }
            if (ch_end == ch_off) { pos = ch_off; continue; }

            if (prev_channel == NULL
                || strlen(prev_channel) != ch_end - ch_off
                || memcmp(prev_channel, block + ch_off, ch_end - ch_off) != 0) {
                long long tag_start_pos = block_pos + (long long)tag_off;
                if (prev_channel != NULL) {
                    sqlite3_bind_text(st, 1, prev_channel, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_int64(st, 2, start_program_block);
                    sqlite3_bind_int64(st, 3, tag_start_pos);
                    sqlite3_step(st);
                    sqlite3_reset(st);
                    rows++;
                    free(prev_channel);
                }
                prev_channel = (char *)malloc(ch_end - ch_off + 1);
                if (prev_channel == NULL) break;
                memcpy(prev_channel, block + ch_off, ch_end - ch_off);
                prev_channel[ch_end - ch_off] = '\0';
                start_program_block = tag_start_pos;
            }
            pos = ch_off;
        }

        if (last_block) {
            if (prev_channel != NULL) {
                const char *tv = mem_find(block, block_len, "</tv>", 5);
                long long end_pos = (tv != NULL)
                    ? block_pos + (long long)(tv - block)
                    : block_pos + (long long)block_len;
                sqlite3_bind_text(st, 1, prev_channel, -1, SQLITE_TRANSIENT);
                sqlite3_bind_int64(st, 2, start_program_block);
                sqlite3_bind_int64(st, 3, end_pos);
                sqlite3_step(st);
                sqlite3_reset(st);
                rows++;
            }
            break;
        }

        block_pos = next_block_pos;
    }

    db_exec(db, CREATE_ENTRIES_INDEX);
    db_exec(db, "COMMIT;");

    free(prev_channel);
    free(block);
    sqlite3_finalize(st);
    return rows;
}

/* ------------------------------------------------------------- commands */
static int cmd_index(const char *xmltv, const char *db_file)
{
    sqlite3 *db = NULL;
    FILE *f;
    long channels, rows;
    double t0;

    f = fopen(xmltv, "rb");
    if (f == NULL) { fprintf(stderr, "cannot open %s\n", xmltv); return 2; }

    if (sqlite3_open(db_file, &db) != SQLITE_OK) {
        fprintf(stderr, "cannot open db %s\n", db_file);
        fclose(f);
        return 2;
    }

    /* match what Sql_Wrapper sets up, so php sees a database it would have made itself */
    db_exec(db, "PRAGMA page_size=4096;");
    db_exec(db, "PRAGMA journal_mode=MEMORY;");
    db_exec(db, "PRAGMA temp_store=FILE;");
    db_exec(db, "PRAGMA synchronous=FULL;");
    /* 3.7.4 on the device reads the file, so do not let a newer library write anything it cannot */
    db_exec(db, "PRAGMA legacy_file_format=ON;");

    db_exec(db, "DROP TABLE IF EXISTS epg_channels;");
    db_exec(db, "DROP TABLE IF EXISTS epg_picons;");
    db_exec(db, "DROP TABLE IF EXISTS epg_entries;");
    if (!db_exec(db, CREATE_CHANNELS) || !db_exec(db, CREATE_PICONS)
        || !db_exec(db, CREATE_ENTRIES) || !db_exec(db, CREATE_STAT)) {
        sqlite3_close(db);
        fclose(f);
        return 2;
    }

    t0 = now_sec();
    channels = index_channels(f, db);
    stat_set(db, "channels", now_sec() - t0);
    if (channels < 0) { fprintf(stderr, "channels pass failed\n"); sqlite3_close(db); fclose(f); return 2; }

    t0 = now_sec();
    rows = index_entries(f, db);
    stat_set(db, "entries", now_sec() - t0);
    if (rows < 0) { fprintf(stderr, "entries pass failed\n"); sqlite3_close(db); fclose(f); return 2; }

    printf("channels=%ld entries=%ld\n", channels, rows);
    sqlite3_close(db);
    fclose(f);
    return 0;
}

static int cmd_blocks(const char *db_file, const char *channel_id, const char *xmltv)
{
    sqlite3 *db = NULL;
    sqlite3_stmt *st = NULL;
    FILE *f = NULL;
    int extract = (xmltv != NULL);

    if (sqlite3_open_v2(db_file, &db, SQLITE_OPEN_READONLY, NULL) != SQLITE_OK) {
        fprintf(stderr, "cannot open db %s\n", db_file);
        return 2;
    }
    if (sqlite3_prepare_v2(db,
            "SELECT start, end FROM epg_entries WHERE channel_id = ? ORDER BY start;",
            -1, &st, NULL) != SQLITE_OK) {
        fprintf(stderr, "cannot prepare lookup\n");
        sqlite3_close(db);
        return 2;
    }
    sqlite3_bind_text(st, 1, channel_id, -1, SQLITE_STATIC);

    if (extract) {
        f = fopen(xmltv, "rb");
        if (f == NULL) { fprintf(stderr, "cannot open %s\n", xmltv); sqlite3_finalize(st); sqlite3_close(db); return 2; }
    }

    while (sqlite3_step(st) == SQLITE_ROW) {
        long long s = sqlite3_column_int64(st, 0);
        long long e = sqlite3_column_int64(st, 1);
        if (!extract) {
            printf("%lld %lld\n", s, e);
            continue;
        }
        if (e <= s) continue;
        if (fseeko(f, (off_t)s, SEEK_SET) != 0) {
            fprintf(stderr, "cannot seek to %lld\n", s);
            continue;
        }
        {
            long long left = e - s;
            char *bufp = (char *)malloc(EXTRACT_CHUNK);
            if (bufp == NULL) break;
            while (left > 0) {
                size_t want = (size_t)(left < EXTRACT_CHUNK ? left : EXTRACT_CHUNK);
                size_t got = fread(bufp, 1, want, f);
                if (got == 0) break;
                fwrite(bufp, 1, got, stdout);
                left -= (long long)got;
            }
            free(bufp);
        }
    }

    if (f != NULL) fclose(f);
    sqlite3_finalize(st);
    sqlite3_close(db);
    return 0;
}

/*
 * Copy [start, start+length) of the file to stdout. This is what php calls for a block whose
 * offset is past the 2Gb it can seek to itself - it replaces one fseek()+fread() and nothing
 * more, so the caller keeps every decision about what the bytes mean.
 */
static int cmd_range(const char *xmltv, const char *start_s, const char *len_s)
{
    FILE *f;
    long long start, left;
    char *bufp;

    start = strtoll(start_s, NULL, 10);
    left = strtoll(len_s, NULL, 10);
    if (start < 0 || left <= 0) { fprintf(stderr, "bad range\n"); return 2; }

    f = fopen(xmltv, "rb");
    if (f == NULL) { fprintf(stderr, "cannot open %s\n", xmltv); return 2; }
    if (fseeko(f, (off_t)start, SEEK_SET) != 0) {
        fprintf(stderr, "cannot seek to %lld\n", start);
        fclose(f);
        return 2;
    }

    bufp = (char *)malloc(EXTRACT_CHUNK);
    if (bufp == NULL) { fclose(f); return 2; }
    while (left > 0) {
        size_t want = (size_t)(left < EXTRACT_CHUNK ? left : EXTRACT_CHUNK);
        size_t got = fread(bufp, 1, want, f);
        if (got == 0) break;
        if (fwrite(bufp, 1, got, stdout) != got) break;
        left -= (long long)got;
    }
    free(bufp);
    fclose(f);
    return 0;
}

static void usage(void)
{
    fprintf(stderr,
        "xmltv_indexer - XMLTV indexer able to address files over 2Gb\n\n"
        "  xmltv_indexer index   <xmltv_file> <db_file>\n"
        "  xmltv_indexer extract <xmltv_file> <db_file> <channel_id>   programme bytes to stdout\n"
        "  xmltv_indexer range   <xmltv_file> <start> <length>         raw bytes to stdout\n"
        "  xmltv_indexer blocks  <db_file> <channel_id>                indexed byte ranges\n");
}

int main(int argc, char **argv)
{
    if (argc >= 2 && strcmp(argv[1], "index") == 0 && argc == 4) {
        return cmd_index(argv[2], argv[3]);
    }
    if (argc >= 2 && strcmp(argv[1], "extract") == 0 && argc == 5) {
        return cmd_blocks(argv[3], argv[4], argv[2]);
    }
    if (argc >= 2 && strcmp(argv[1], "range") == 0 && argc == 5) {
        return cmd_range(argv[2], argv[3], argv[4]);
    }
    if (argc >= 2 && strcmp(argv[1], "blocks") == 0 && argc == 4) {
        return cmd_blocks(argv[2], argv[3], NULL);
    }
    usage();
    return 1;
}
