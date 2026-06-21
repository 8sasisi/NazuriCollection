-- Initial seed data for Grant Fashions (safe, idempotent)

-- Site settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('shop_name','GRANT FASHIONS'),
('logo',''),
('phone','0767557234'),
('meta_description','Grant Fashions - Elegant Abayas & Gowns'),
('site_url','http://localhost/grant_folder-main')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- One active slider (status stored as 1)
INSERT INTO sliders (title, subtitle, button_text, button_link, video_path, sort_order, status) VALUES
('Karibu Grant Fashions','Ng\'ara na hadhi','Nenda Duka','shop.php','',0,1)
ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), button_text=VALUES(button_text), button_link=VALUES(button_link), video_path=VALUES(video_path), sort_order=VALUES(sort_order), status=VALUES(status);

-- Sample products (assumes two sample images already present in uploads/)
INSERT INTO products (name, description, price, category, image, status, discount_price, coupon_code, product_code, offer_badge, discount_percentage) VALUES
('Classic Abaya','Elegant black abaya with embroidery',50000,'abaya','69cbe319476fe.png','active',45000,NULL,'ABAYA001',1,10),
('Evening Gown','Flowing evening gown',120000,'gown','69cbe79e33d30.png','active',0,NULL,'GOWN001',0,0),
('Two Piece Set','Stylish two piece set',80000,'two_pieces','69cbe319476fe.png','active',70000,NULL,'TWOPIECE001',0,12),
('Casual Guberi','Comfortable guberi top',30000,'guberi','69cbe79e33d30.png','active',0,NULL,'GUBERI001',0,0)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), price=VALUES(price), category=VALUES(category), image=VALUES(image), status=VALUES(status), discount_price=VALUES(discount_price), coupon_code=VALUES(coupon_code), product_code=VALUES(product_code), offer_badge=VALUES(offer_badge), discount_percentage=VALUES(discount_percentage);

-- Sample gallery entries (for fresh DB these reference product IDs 1-4)
INSERT INTO product_gallery (product_id, image) VALUES
(1,'69cbe319476fe.png'),
(2,'69cbe79e33d30.png'),
(3,'69cbe319476fe.png'),
(4,'69cbe79e33d30.png')
ON DUPLICATE KEY UPDATE image=VALUES(image);

-- Approved review for product 1
INSERT INTO reviews (product_id, customer_name, rating, comment, status) VALUES
(1,'Asha',5,'Beautiful quality and fast delivery. Highly recommend!', 'approved');
