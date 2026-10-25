PRAGMA foreign_keys=ON;
CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,password TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'customer' CHECK(role IN ('customer','admin')),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE categories(id INTEGER PRIMARY KEY,name TEXT NOT NULL UNIQUE);
CREATE TABLE products(id INTEGER PRIMARY KEY,category_id INTEGER NOT NULL REFERENCES categories(id),name TEXT NOT NULL,description TEXT NOT NULL,price INTEGER NOT NULL CHECK(price>=0),stock INTEGER NOT NULL CHECK(stock>=0),image TEXT NOT NULL DEFAULT '',active INTEGER NOT NULL DEFAULT 1 CHECK(active IN(0,1)));
CREATE TABLE coupons(id INTEGER PRIMARY KEY,code TEXT NOT NULL UNIQUE,percent INTEGER NOT NULL CHECK(percent BETWEEN 1 AND 100),min_total INTEGER NOT NULL DEFAULT 0 CHECK(min_total>=0),expires_at TEXT,active INTEGER NOT NULL DEFAULT 1 CHECK(active IN(0,1)));
CREATE TABLE orders(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL REFERENCES users(id),name TEXT NOT NULL,phone TEXT NOT NULL,address TEXT NOT NULL,payment TEXT NOT NULL CHECK(payment IN('cod','bank')),status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN('pending','confirmed','shipping','completed','cancelled')),paid INTEGER NOT NULL DEFAULT 0 CHECK(paid IN(0,1)),subtotal INTEGER NOT NULL,discount INTEGER NOT NULL,shipping INTEGER NOT NULL,total INTEGER NOT NULL,coupon TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE order_items(id INTEGER PRIMARY KEY,order_id INTEGER NOT NULL REFERENCES orders(id),product_id INTEGER NOT NULL REFERENCES products(id),name TEXT NOT NULL,price INTEGER NOT NULL,qty INTEGER NOT NULL CHECK(qty>0));
CREATE TABLE login_attempts(email TEXT PRIMARY KEY,attempts INTEGER NOT NULL DEFAULT 0,locked_until INTEGER NOT NULL DEFAULT 0);
CREATE INDEX products_category ON products(category_id,active);
CREATE INDEX orders_user ON orders(user_id,created_at);

CREATE TABLE IF NOT EXISTS quote_requests(id INTEGER PRIMARY KEY,product_id INTEGER REFERENCES products(id),name TEXT NOT NULL,phone TEXT NOT NULL,material TEXT NOT NULL,dimensions TEXT NOT NULL,location TEXT NOT NULL,notes TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'new' CHECK(status IN('new','contacted','quoted','closed')),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS site_settings(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS quote_requests_status_id ON quote_requests(status,id DESC);
CREATE INDEX IF NOT EXISTS products_active_id ON products(active,id);
