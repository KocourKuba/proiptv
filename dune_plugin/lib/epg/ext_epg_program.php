<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

class Ext_Epg_Program extends PluginTvEpgProgram
{
    const    /* (char *)	*/
        sub_title = 'sub-title';        // подзаголовок телепередачи
    const    /* (char *)	*/
        main_category = 'main_category';    // категория, жанр, возрастной ценз
    const    /* (char *)	*/
        main_icon = 'main_icon';        // титульная картинка (рекомендуемый размер 400х300)
    const    /* array	*/
        icon_urls = 'icons';            // дополнительные картинки (рекомендуемый размер 400х300)
    const    /* (char *)	*/
        year = 'year';            // год выпуска (в прокате)
    const    /* (char *)	*/
        country = 'country';        // страна
    const    /* array	*/
        director = 'director';        // режиссер(ы)
    const    /* array	*/
        producer = 'producer';        // продюсер(ы)
    const    /* array	*/
        actor = 'actor';            // актер(ы)
    const    /* array	*/
        presenter = 'presenter';        // ведущий(е)
    const    /* array	*/
        writer = 'writer';            // сценарист(ы)
    const    /* array	*/
        operator = 'editor';            // оператор(ы)
    const    /* array	*/
        composer = 'composer';        // композитор(ы)
    const    /* (char *)	*/
        imdb_rating = 'imdb_rating';    // рейтинг IMDB
    const    /* (char *)	*/
        kp_rating = 'kp_rating';        // рейтинг Кинопоиск
}
