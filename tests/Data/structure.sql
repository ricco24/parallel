SET FOREIGN_KEY_CHECKS=0;

create table products (
  id int not null primary key auto_increment,
  title varchar(255),
  image varchar(255),
  type_id int not null,
  price int not null,
  is_deleted int(1) default false,
  is_hidden int(1) default false,

  foreign key (type_id) references product_types(id) on update cascade on delete cascade
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

create table product_types(
  id int not null primary key auto_increment,
  title varchar(255) not null,
  image varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

create table product_categories(
  id int not null primary key auto_increment,
  title varchar(255) not null
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

create table products_product_categories(
  id int not null primary key auto_increment,
  product_id int not null,
  product_category_id int not null,
  sorting int not null,

  foreign key (product_id) references products(id) on update cascade on delete cascade,
  foreign key (product_category_id) references product_categories(id) on update cascade on delete cascade
) ENGINE=InnoDB DEFAULT CHARSET=utf8;