START TRANSACTION;

CREATE TEMPORARY TABLE missing_product_size_groups (
  supplier VARCHAR(120) NOT NULL,
  product VARCHAR(150) NOT NULL,
  color VARCHAR(80) NOT NULL,
  size_group VARCHAR(20) NOT NULL
);

INSERT INTO missing_product_size_groups (supplier, product, color, size_group) VALUES
('H&M', 'BAGGY DENIM SHORTS', 'Denim gray', 'hm_alpha'),
('H&M', 'BAGGY JEANS', 'Washed dark denim gray', 'hm_alpha'),
('H&M', 'LOOSE-FIT COTTON CARGO SHORTS', 'Khaki green', 'hm_alpha'),
('H&M', 'ORIGINAL STRAIGHT JEANS', 'Denim black', 'hm_alpha'),
('H&M', 'OVERSHIRT', 'Dark brown', 'hm_alpha'),
('H&M', 'OVERSIZED PRINTED HOODIE', 'Dark blue/Las Vegas', 'hm_alpha'),
('H&M', 'REGULAR-FIT CARDIGAN', 'Dark gray melange', 'hm_alpha'),
('H&M', 'REGULAR-FIT FINE-KNIT SWEATER', 'Dark taupe', 'hm_alpha'),
('H&M', 'REGULAR-FIT HALF-ZIP SWEATER', 'Black', 'hm_alpha'),
('H&M', 'REGULAR-FIT LINEN-BLEND SHORTS', 'White', 'hm_alpha'),
('H&M', 'RELAXED-FIT COTTON CHINOS', 'Dark brown', 'hm_alpha'),
('H&M', 'RELAXED-FIT COTTON TWILL CHINOS', 'Navy blue', 'hm_alpha'),
('H&M', 'RELAXED-FIT DENIM SHORTS', 'Denim black', 'hm_alpha'),
('H&M', 'RELAXED-FIT HALF-ZIP SWEATSHIRT', 'Light gray melange', 'hm_alpha'),
('H&M', 'ULTRA-BAGGY JEANS', 'Washed light denim blue', 'hm_alpha'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Burgundy Crush', 'nike_alpha_5'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Black/Black', 'nike_alpha_6'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Tattoo/Black/Burgundy Crush', 'nike_alpha_5'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Black/Black/Dark Smoke Grey', 'nike_alpha_5'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Photon Dust/White', 'nike_alpha_6'),
('Nike', 'Nike Sportswear T-Shirt', 'Black', 'nike_alpha_5'),
('Nike', 'Nike Sportswear T-Shirt', 'Old Royal', 'nike_alpha_5'),
('Nike', 'Nike Sportswear T-Shirt', 'White', 'nike_alpha_5');

CREATE TEMPORARY TABLE missing_product_sizes (
  size_group VARCHAR(20) NOT NULL,
  size VARCHAR(20) NOT NULL
);

INSERT INTO missing_product_sizes (size_group, size) VALUES
('hm_alpha', 'XS'), ('hm_alpha', 'S'), ('hm_alpha', 'M'),
('hm_alpha', 'L'), ('hm_alpha', 'XL'), ('hm_alpha', 'XXL'),
('nike_alpha_5', 'S'), ('nike_alpha_5', 'M'), ('nike_alpha_5', 'L'),
('nike_alpha_5', 'XL'), ('nike_alpha_5', '2XL'),
('nike_alpha_6', 'XS'), ('nike_alpha_6', 'S'), ('nike_alpha_6', 'M'),
('nike_alpha_6', 'L'), ('nike_alpha_6', 'XL'), ('nike_alpha_6', '2XL');

INSERT IGNORE INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, options.size, 5
FROM missing_product_size_groups AS missing
JOIN missing_product_sizes AS options ON options.size_group = missing.size_group
JOIN suppliers AS s ON s.name = missing.supplier
JOIN products AS p ON p.supplier_id = s.supplier_id AND p.name = missing.product
JOIN product_colors AS pc ON pc.product_id = p.product_id AND pc.color = missing.color;

DROP TEMPORARY TABLE missing_product_sizes;
DROP TEMPORARY TABLE missing_product_size_groups;

COMMIT;
