create table versions(
  id int not null primary key auto_increment,
  linked_id int(11),
  transaction_id varchar(255),
  foreign_id varchar(255) not null,
  foreign_table varchar(255) not null,
  user_id int,
  old_data text,
  new_data text,
  flag varchar(50),
  created_at datetime not null,
  foreign key(`linked_id`) references versions(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE INDEX versions_history ON versions (created_at, foreign_id, foreign_table);
