<?php

class Ext_Epg_Program extends PluginTvEpgProgram
{
	const	/* (char *)	*/	sub_title			= 'sub-title';		// подзаголовок телепередачи
	const	/* (char *)	*/	main_category		= 'main_category';	// категория, жанр, возрастной ценз
    const	/* (char *)	*/	main_icon			= 'main_icon';		// титульная картинка (рекомендуемый размер 400х300)
	const	/* array	*/	icon_urls			= 'icons';			// дополнительные картинки (рекомендуемый размер 400х300)
	const	/* (char *)	*/	year				= 'year';			// год выпуска (в прокате)
	const	/* (char *)	*/	country				= 'country';		// страна
	const	/* array	*/	director			= 'director';		// режиссер(ы)
	const	/* array	*/	producer			= 'producer';		// продюсер(ы)
	const	/* array	*/	actor				= 'actor';			// актер(ы)
	const	/* array	*/	presenter			= 'presenter';		// ведущий(е)
	const	/* array	*/	writer				= 'writer';			// сценарист(ы)
	const	/* array	*/	operator			= 'editor';			// оператор(ы)
	const	/* array	*/	composer			= 'composer';		// композитор(ы)
	const	/* (char *)	*/	imdb_rating			= 'imdb_rating';	// рейтинг IMDB
	const	/* (char *)	*/	kp_rating			= 'kp_rating';		// рейтинг Кинопоиск
}
