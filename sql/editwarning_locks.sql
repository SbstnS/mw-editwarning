CREATE TABLE IF NOT EXISTS editwarning_locks (
  user_id int(10) unsigned NOT NULL,
  user_name varchar(255) NOT NULL,
  article_id int(10) unsigned NOT NULL,
  timestamp int(11) unsigned NOT NULL,
  section int(2) unsigned NOT NULL,
  KEY user_id (user_id),
  KEY article_id (article_id)
) ENGINE=InnoDB;

ALTER TABLE editwarning_locks
  ADD CONSTRAINT editwarning_locks_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT editwarning_locks_ibfk_2 FOREIGN KEY (article_id) REFERENCES page (page_id) ON DELETE CASCADE ON UPDATE CASCADE;