CREATE TABLE IF NOT EXISTS `password_less_login` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `email` varchar(300) NOT NULL,
  `login_code` varchar(300) NOT NULL,
  `was_used` tinyint(1) NOT NULL,
  `generated_at` int(30) NOT NULL,
  `ip` varchar(15) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;