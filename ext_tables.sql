#
# Table structure for table 'tt_content'
#
CREATE TABLE tt_content (
	colPos int(11) DEFAULT '0' NOT NULL,
	layout varchar(255) DEFAULT '' NOT NULL,
	backupColPos int(11) DEFAULT '-2' NOT NULL,
	tx_gridelements_backend_layout varchar(255) DEFAULT '' NOT NULL,
	tx_gridelements_children int(11) DEFAULT '0' NOT NULL,
	tx_gridelements_container int(11) DEFAULT '0' NOT NULL,
	tx_gridelements_columns int(11) DEFAULT '0' NOT NULL,
	gridelements_shortcut_page_order_by int(11) DEFAULT '0' NOT NULL,

	KEY gridelements (tx_gridelements_container,tx_gridelements_columns)
);
