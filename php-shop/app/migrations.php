<?php
declare(strict_types=1);
function migrateAdmin(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        foreach(['users'=>['access_level'=>"TEXT NOT NULL DEFAULT 'owner'",'active'=>'INTEGER NOT NULL DEFAULT 1','auth_version'=>'INTEGER NOT NULL DEFAULT 1','totp_secret'=>"TEXT NOT NULL DEFAULT ''",'totp_last'=>'INTEGER NOT NULL DEFAULT 0'], 'categories'=>['parent_id'=>'INTEGER REFERENCES categories(id)','sort_order'=>'INTEGER NOT NULL DEFAULT 0'], 'quote_requests'=>['internal_notes'=>"TEXT NOT NULL DEFAULT ''",'shipping'=>'INTEGER NOT NULL DEFAULT 0','deposit'=>'INTEGER NOT NULL DEFAULT 0','valid_until'=>"TEXT NOT NULL DEFAULT ''"]] as $table=>$columns) {
            $existing=$pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_COLUMN,1);
            foreach($columns as $name=>$type) if(!in_array($name,$existing,true)) $pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$type);
        }
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media(id INTEGER PRIMARY KEY,filename TEXT NOT NULL UNIQUE,original_name TEXT NOT NULL,mime TEXT NOT NULL,size INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS product_images(product_id INTEGER NOT NULL REFERENCES products(id),media_id INTEGER NOT NULL REFERENCES media(id),sort_order INTEGER NOT NULL DEFAULT 0,PRIMARY KEY(product_id,media_id));
CREATE TABLE IF NOT EXISTS content(id INTEGER PRIMARY KEY,type TEXT NOT NULL CHECK(type IN('article','project','award','page')),slug TEXT NOT NULL UNIQUE,title TEXT NOT NULL,body TEXT NOT NULL,image TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN('draft','published')),seo_title TEXT NOT NULL DEFAULT '',seo_description TEXT NOT NULL DEFAULT '',updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS quote_lines(id INTEGER PRIMARY KEY,quote_id INTEGER NOT NULL REFERENCES quote_requests(id),description TEXT NOT NULL,qty INTEGER NOT NULL CHECK(qty>0),price INTEGER NOT NULL CHECK(price>=0));
CREATE TABLE IF NOT EXISTS audit_log(id INTEGER PRIMARY KEY,user_id INTEGER,event TEXT NOT NULL,target TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS password_resets(token_hash TEXT PRIMARY KEY,user_id INTEGER NOT NULL REFERENCES users(id),expires_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS notifications(id INTEGER PRIMARY KEY,recipient TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN('pending','sent','failed')),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS content_public ON content(type,status,id DESC);
CREATE INDEX IF NOT EXISTS quote_lines_quote ON quote_lines(quote_id);
CREATE INDEX IF NOT EXISTS audit_recent ON audit_log(id DESC);
SQL);
        $pdo->commit();
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}
