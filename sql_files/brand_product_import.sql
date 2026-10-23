-- Verified product listings and colorways from the sources documented in product_links.txt.
-- Additional Nike colorways are included where their official pages were accessible.
-- H&M and PENSHOPPE pages blocked follow-up checks, so their previously recorded colors remain.
-- Import into the existing soigne_apparel database after applying the main schema.
-- For an existing database, run migrate_product_categories.sql before this import.
-- Images are linked to filenames in images/ when a matching image is available.
-- Unmatched or color-mismatched images remain NULL. Availability/stock is initialized to 0.
-- Product-page URLs and research limitations are documented in product_links.txt.

ALTER TABLE product_colors
  MODIFY color VARCHAR(80) NOT NULL;

START TRANSACTION;

UPDATE product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
SET v.size = CASE p.name
  WHEN 'Cotton T-shirt' THEN CASE v.size
    WHEN '92' THEN 'XS'
    WHEN '98/104' THEN 'S'
    WHEN '110/116' THEN 'M'
    WHEN '122/128' THEN 'L'
    WHEN '134/140' THEN 'XL'
  END
  WHEN 'Cotton Jersey T-shirt' THEN CASE v.size
    WHEN '134/140' THEN 'S'
    WHEN '146/152' THEN 'M'
    WHEN '158/164' THEN 'L'
    WHEN '170' THEN 'XL'
  END
END
WHERE s.name = 'H&M'
  AND p.department = 'kids'
  AND p.name IN ('Cotton T-shirt', 'Cotton Jersey T-shirt')
  AND v.size IN ('92', '98/104', '110/116', '122/128', '134/140', '146/152', '158/164', '170');

UPDATE product_colors AS pc
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
SET pc.color = 'Black',
    pc.image = 'Nike_T-Shirt_NS_BLK.jpg'
WHERE s.name = 'Nike'
  AND p.name = 'Nike Sportswear Women''s T-Shirt'
  AND p.department = 'women'
  AND pc.color = 'Black/White';

INSERT INTO suppliers (name)
VALUES ('PENSHOPPE')
ON DUPLICATE KEY UPDATE supplier_id = LAST_INSERT_ID(supplier_id);

INSERT INTO suppliers (name)
VALUES ('Nike')
ON DUPLICATE KEY UPDATE supplier_id = LAST_INSERT_ID(supplier_id);

INSERT INTO suppliers (name)
VALUES ('H&M')
ON DUPLICATE KEY UPDATE supplier_id = LAST_INSERT_ID(supplier_id);

CREATE TEMPORARY TABLE tmp_brand_products (
  brand VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  color VARCHAR(80) NOT NULL,
  department VARCHAR(20) NOT NULL DEFAULT 'men'
);

CREATE TEMPORARY TABLE tmp_brand_images (
  brand VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  color VARCHAR(80) NOT NULL,
  image VARCHAR(255) NOT NULL
);

INSERT INTO tmp_brand_images (brand, name, color, image) VALUES
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'Penshoppe_T-Shirt_PBG_BLK.jpg'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'Penshoppe_Shirt_BS_RED.jpg'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'Penshoppe_Polo_DCJP_BLK.jpg'),
('PENSHOPPE', 'Essentials Relaxed Pullover Hoodie', 'Evening Sand', 'Penshoppe_Hoodie_ERPH_ES.jpg'),
('PENSHOPPE', 'Dress Code Relaxed Knit Sweater', 'Midnight Navy', 'Penshoppe_Sweater_DCRKS_MN.jpg'),
('PENSHOPPE', 'Dress Code Comfort Slim Fit Pants', 'Dark Gray', 'Penshoppe_Pants_DCSFP_DGY.jpg'),
('PENSHOPPE', 'Essentials Classic Straight Fit Jeans', 'Light Blue', 'Penshoppe_Jeans_ECSFJ_LBL.jpg'),
('PENSHOPPE', 'Slim Fit Ankle Length Chinos', 'Black', 'Penshoppe_Chinos_SFALC_BEI.jpg'),
('PENSHOPPE', 'Essentials Modern Fit Sports Shorts', 'Black', 'Penshoppe_Shorts_EMFSS_BLK.jpg'),
('PENSHOPPE', 'Penshoppe Play Windbreaker Jacket', 'Black', 'Penshoppe_Outerwear_PPW_BLK.jpg'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'Nike_T-Shirts_JSSJ_BLK.jpg'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', 'Nike_T-Shirts_NSL_LB.jpg'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', 'Nike_T-Shirt_NSL_ANTH.jpg'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', 'Nike_T-Shirt_NSW_FIR.jpg'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', 'Nike_T-Shirt_NSW_TRD.jpg'),
('Nike', 'Nike Sportswear T-Shirt', 'Black', 'Nike_T-Shirt_NSW_BLK.jpg'),
('Nike', 'Nike Sportswear T-Shirt', 'White', 'Nike_T-Shirt_NSW_WHT.jpg'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'Nike_Shirt_NCSBSU_OW.jpg'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', 'Nike_Shirt_N24DFUVBU_LOBSLIO.jpg'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Tattoo/Black/Burgundy Crush', 'Nike_Shirt_N24DFUVBU_TBBCR.jpg'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Black/Black/Dark Smoke Grey', 'Nike_Shirt_N24DFUVBU_BBDSG.jpg'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'Nike_Polo_JSMGP_JSJHJS.jpg'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Black/Black', 'Nike_Polo_JSMGP_BLK.jpg'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'Nike_Hoodie_JMBH_BLK.jpg'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Burgundy Crush', 'Nike_Hoodie_JMBH_BC.jpg'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'Nike_Pants_PSGFT_BLK.jpg'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', 'Nike_Shorts_NMDF10_SPBB.jpg'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'Nike_Outerwear_ACGDG_BLK.jpg'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'H&M_T-Shirt_3PRF_BLKBG.jpg'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'H&M_T-Shirt_SFT_BLK.jpg'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'H&M_T-Shirt_OFT_YMAT.jpg'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'H&M_T-Shirt_RFT_BEI.jpg'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'H&M_T-Shirt_RFXT_BLU.jpg'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'H&M_Shirt_RFCS_BURG.jpg'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'H&M_Shirt_LFSSDS_LBR.jpg'),
('H&M', 'OVERSHIRT', 'Dark brown', 'H&M_Shirt_OVSHT_DBR.jpg'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'H&M_Shirt_LFPSS_LYV.jpg'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Navy blue', 'H&M_Shirt_FWSRFS_NVB.jpg'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'H&M_Polo_CRFP_BLK.jpg'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'H&M_Polo_RFP_BIM.jpg'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'H&M_Polo_LFCHZP_BLK.jpg'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'H&M_Polo_LFCPS_LGMS.jpg'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'H&M_Polo_RFP_WHT.jpg'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'H&M_Hoodie_RFH_DGY.jpg'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'H&M_Hoodie_RFS_NVB.jpg'),
('H&M', 'RELAXED-FIT HALF-ZIP SWEATSHIRT', 'Light gray melange', 'H&M_Hoodie_RFHZS_LGM.jpg'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'H&M_Hoodie_LFSH_DBR.jpg'),
('H&M', 'OVERSIZED PRINTED HOODIE', 'Dark blue/Las Vegas', 'H&M_Hoodie_OPH_DBLV.jpg'),
('H&M', 'REGULAR-FIT HALF-ZIP SWEATER', 'Black', 'H&M_Sweater_RFHZS_BLK.jpg'),
('H&M', 'REGULAR-FIT CARDIGAN', 'Dark gray melange', 'H&M_Sweater_RFC_DGM.jpg'),
('H&M', 'REGULAR-FIT FINE-KNIT SWEATER', 'Dark taupe', 'H&M_Sweater_RFFKS_DTP.jpg'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'H&M_Sweater_RFFKTS_BLK.jpg'),
('H&M', 'BAGGY JEANS', 'Washed dark denim gray', 'H&M_Jeans_BJ_WDDG.jpg'),
('H&M', 'ORIGINAL STRAIGHT JEANS', 'Denim black', 'H&M_Jeans_OSJ_DBK.jpg'),
('H&M', 'ULTRA-BAGGY JEANS', 'Washed light denim blue', 'H&M_Jeans_UBJ_WLDB.jpg'),
('H&M', 'RELAXED JEANS', 'Light denim blue', 'H&M_Jeans_RJ_LDB.jpg'),
('H&M', 'RELAXED-FIT COTTON TWILL CHINOS', 'Navy blue', 'H&M_Chinos_RFCTC_NVB.jpg'),
('H&M', 'RELAXED-FIT COTTON CHINOS', 'Dark brown', 'H&M_Chinos_RFC_DBR.jpg'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'H&M_Shorts_RFS_LGM.jpg'),
('H&M', 'LOOSE-FIT COTTON CARGO SHORTS', 'Khaki green', 'H&M_Shorts_LFCCS_KHG.jpg'),
('H&M', 'RELAXED-FIT DENIM SHORTS', 'Denim black', 'H&M_Shorts_RFDS_DBK.jpg'),
('H&M', 'REGULAR-FIT LINEN-BLEND SHORTS', 'White', 'H&M_Shorts_RFLBS_WHT.jpg'),
('H&M', 'BAGGY DENIM SHORTS', 'Denim gray', 'H&M_Shorts_BDS_DG.jpg'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'H&M_Outerwear_WWRJ_KHG.jpg');

INSERT INTO tmp_brand_images (brand, name, color, image) VALUES
('H&M', 'Oxford Shirt', 'White', 'H&M_Shirt_OS_WHT.jpg'),
('H&M', 'Oxford Shirt', 'Blue', 'H&M_Shirt_OS_BLUE.jpg'),
('H&M', 'Oxford Shirt', 'Light Pink', 'H&M_Shirt_OS_LP.jpg'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'H&M_T-Shirt_KTS_BM.jpg'),
('H&M', 'Knit T-Shirt', 'Cream', 'H&M_T-Shirt_KTS_CREAM.jpg'),
('H&M', 'Cotton T-shirt', 'White', 'H&M_T-Shirt_CTS_WHT.jpg'),
('H&M', 'Cotton T-shirt', 'Light Red', 'H&M_T-Shirt_CTS_LR.jpg'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'H&M_T-Shirt_CTS_LT.jpg'),
('H&M', 'Cotton Jersey T-shirt', 'Cerise', 'H&M_T-Shirt_CJTS_CERISE.jpg'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'Nike_T-Shirt_NS_BLK.jpg'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'Nike_T-Shirt_NS_WHT.jpg'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'Nike_T-Shirt_NSLT_BLK.jpg'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'Nike_T-Shirt_NSK_WHT.jpg'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'Nike_T-Shirt_NSOK_BLK.jpg'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'Nike_T-Shirt_NSOK_WHT.jpg'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'Penshoppe_T-Shirt_LPMSGTS_OW.jpg'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'Penshoppe_T-Shirt_CMSGTS_BLK.jpg'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'Penshoppe_T-Shirt_ERSTS_RED.jpg'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'Penshoppe_Shirt_LSSBDTB_BLU.jpg');

INSERT INTO tmp_brand_products (brand, name, category, price, color) VALUES
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'T-shirts', 549.00, 'Black'),
('PENSHOPPE', 'Boxy Shirt', 'Shirts', 899.00, 'Red'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Polos', 799.00, 'Black'),
('PENSHOPPE', 'Essentials Relaxed Pullover Hoodie', 'Hoodies', 1099.00, 'Evening Sand'),
('PENSHOPPE', 'Dress Code Relaxed Knit Sweater', 'Sweaters', 649.00, 'Midnight Navy'),
('PENSHOPPE', 'Dress Code Comfort Slim Fit Pants', 'Pants', 1799.00, 'Dark Gray'),
('PENSHOPPE', 'Essentials Classic Straight Fit Jeans', 'Jeans', 1499.00, 'Light Blue'),
('PENSHOPPE', 'Slim Fit Ankle Length Chinos', 'Chinos', 899.00, 'Black'),
('PENSHOPPE', 'Essentials Modern Fit Sports Shorts', 'Shorts', 799.00, 'Black'),
('PENSHOPPE', 'Penshoppe Play Windbreaker Jacket', 'Outerwear', 1599.00, 'Black'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'T-shirts', 2095.00, 'Black'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'T-shirts', 2095.00, 'Light Bone'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'T-shirts', 2095.00, 'Anthracite'),
('Nike', 'Nike Sportswear T-Shirt', 'T-shirts', 1795.00, 'Fir'),
('Nike', 'Nike Sportswear T-Shirt', 'T-shirts', 1795.00, 'Old Royal'),
('Nike', 'Nike Sportswear T-Shirt', 'T-shirts', 1795.00, 'Tough Red'),
('Nike', 'Nike Sportswear T-Shirt', 'T-shirts', 1795.00, 'Black'),
('Nike', 'Nike Sportswear T-Shirt', 'T-shirts', 1795.00, 'White'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Shirts', 2895.00, 'Obsidian/White'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Shirts', 2895.00, 'Photon Dust/White'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Shirts', 2937.00, 'Light Orewood Brown/Sail/Light Iron Ore'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Shirts', 2937.00, 'Tattoo/Black/Burgundy Crush'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Shirts', 2937.00, 'Black/Black/Dark Smoke Grey'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Polos', 5695.00, 'Jade Smoke/Jade Horizon/Jade Smoke'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Polos', 5695.00, 'Black/Black'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Hoodies', 3795.00, 'Black'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Hoodies', 3795.00, 'Burgundy Crush'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Pants', 3195.00, 'Black'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Shorts', 3295.00, 'Space Purple'),
('Nike', 'ACG Dolomiti Gilet', 'Outerwear', 5395.00, 'Black/Summit White'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'T-shirts', 999.00, 'Black/Burgundy'),
('H&M', 'SLIM FIT T-SHIRT', 'T-shirts', 399.00, 'Black'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'T-shirts', 999.00, 'Yellow/Matisse'),
('H&M', 'REGULAR FIT T-SHIRT', 'T-shirts', 399.00, 'Beige'),
('H&M', 'RELAXED-FIT T-SHIRT', 'T-shirts', 499.00, 'Blue'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Shirts', 1290.00, 'Burgundy'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Shirts', 1790.00, 'Light blue/Rerun'),
('H&M', 'OVERSHIRT', 'Shirts', 2490.00, 'Dark brown'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Shirts', 1790.00, 'Light yellow/Vibrant'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Shirts', 1490.00, 'Navy blue'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Polos', 899.00, 'Black'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Polos', 1490.00, 'Black/Iron Maiden'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Polos', 999.00, 'Black'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Polos', 1790.00, 'Light gray melange/striped'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'Polos', 699.00, 'White'),
('H&M', 'RELAXED-FIT HOODIE', 'Hoodies', 999.00, 'Dark gray'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Hoodies', 899.00, 'Navy blue'),
('H&M', 'RELAXED-FIT HALF-ZIP SWEATSHIRT', 'Hoodies', 1490.00, 'Light gray melange'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Hoodies', 1990.00, 'Dark brown'),
('H&M', 'OVERSIZED PRINTED HOODIE', 'Hoodies', 1990.00, 'Dark blue/Las Vegas'),
('H&M', 'REGULAR-FIT HALF-ZIP SWEATER', 'Sweaters', 1790.00, 'Black'),
('H&M', 'REGULAR-FIT CARDIGAN', 'Sweaters', 1990.00, 'Dark gray melange'),
('H&M', 'REGULAR-FIT FINE-KNIT SWEATER', 'Sweaters', 1290.00, 'Dark taupe'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Sweaters', 1790.00, 'Black'),
('H&M', 'BAGGY JEANS', 'Jeans', 1990.00, 'Washed dark denim gray'),
('H&M', 'ORIGINAL STRAIGHT JEANS', 'Jeans', 1990.00, 'Denim black'),
('H&M', 'ULTRA-BAGGY JEANS', 'Jeans', 2490.00, 'Washed light denim blue'),
('H&M', 'RELAXED JEANS', 'Jeans', 1790.00, 'Light denim blue'),
('H&M', 'RELAXED-FIT COTTON TWILL CHINOS', 'Chinos', 1990.00, 'Navy blue'),
('H&M', 'RELAXED-FIT COTTON CHINOS', 'Chinos', 1790.00, 'Dark brown'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Shorts', 699.00, 'Light gray melange'),
('H&M', 'LOOSE-FIT COTTON CARGO SHORTS', 'Shorts', 1990.00, 'Khaki green'),
('H&M', 'RELAXED-FIT DENIM SHORTS', 'Shorts', 1790.00, 'Denim black'),
('H&M', 'REGULAR-FIT LINEN-BLEND SHORTS', 'Shorts', 1490.00, 'White'),
('H&M', 'BAGGY DENIM SHORTS', 'Shorts', 1990.00, 'Denim gray'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Outerwear', 2490.00, 'Khaki green');

-- Additional verified women's and kids' products and uploaded colorways.
INSERT INTO tmp_brand_products (brand, name, category, price, color, department) VALUES
('Nike', 'Nike Sportswear Women''s T-Shirt', 'T-shirts', 1045.00, 'Black', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'T-shirts', 1045.00, 'White', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'T-shirts', 1077.00, 'Black', 'women'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'T-shirts', 627.00, 'White', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'T-shirts', 1045.00, 'Black', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'T-shirts', 1045.00, 'White', 'kids'),
('H&M', 'Oxford Shirt', 'Shirts', 749.00, 'White', 'women'),
('H&M', 'Oxford Shirt', 'Shirts', 749.00, 'Blue', 'women'),
('H&M', 'Oxford Shirt', 'Shirts', 749.00, 'Light Pink', 'women'),
('H&M', 'Knit T-Shirt', 'T-shirts', 599.00, 'Beige melange', 'women'),
('H&M', 'Knit T-Shirt', 'T-shirts', 599.00, 'Cream', 'women'),
('H&M', 'Cotton T-shirt', 'T-shirts', 299.00, 'White', 'kids'),
('H&M', 'Cotton T-shirt', 'T-shirts', 299.00, 'Light Red', 'kids'),
('H&M', 'Cotton T-shirt', 'T-shirts', 299.00, 'Light Turqoise', 'kids'),
('H&M', 'Cotton Jersey T-shirt', 'T-shirts', 399.00, 'Cerise', 'kids'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'T-shirts', 499.00, 'Off White', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'T-shirts', 499.00, 'Black', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'T-shirts', 499.00, 'Red', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Shirts', 1199.00, 'Blush', 'women');

INSERT INTO products (supplier_id, name, category_id, category, department, price, description)
SELECT suppliers.supplier_id, source.name, categories.category_id, source.category, source.department, source.price, NULL
FROM (
  SELECT DISTINCT brand, name, category, price, department
  FROM tmp_brand_products
) AS source
JOIN suppliers ON suppliers.name = source.brand
JOIN categories ON categories.name = source.category
WHERE NOT EXISTS (
  SELECT 1
  FROM products AS existing
  WHERE existing.supplier_id = suppliers.supplier_id
    AND existing.name = source.name
    AND existing.department = source.department
);

INSERT INTO product_colors (product_id, color, image)
SELECT DISTINCT p.product_id, source.color, images.image
FROM tmp_brand_products AS source
JOIN suppliers ON suppliers.name = source.brand
JOIN products AS p
  ON p.supplier_id = suppliers.supplier_id
 AND p.name = source.name
 AND p.department = source.department
LEFT JOIN tmp_brand_images AS images
  ON images.brand = source.brand
 AND images.name = source.name
 AND images.color = source.color
WHERE TRUE
ON DUPLICATE KEY UPDATE image = COALESCE(VALUES(image), product_colors.image);

CREATE TEMPORARY TABLE tmp_brand_sizes (
  brand VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  color VARCHAR(80) NOT NULL,
  size VARCHAR(20) NOT NULL,
  department VARCHAR(20) NOT NULL DEFAULT 'men'
);

INSERT INTO tmp_brand_sizes (brand, name, color, size) VALUES
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'XS'),
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'S'),
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'M'),
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'L'),
('PENSHOPPE', 'Prodigy Boxy Graphic T-Shirt', 'Black', 'XL'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'XS'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'S'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'M'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'L'),
('PENSHOPPE', 'Boxy Shirt', 'Red', 'XL'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'XS'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'S'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'M'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'L'),
('PENSHOPPE', 'Dress Code Jacquard Polo', 'Black', 'XL'),
('PENSHOPPE', 'Essentials Relaxed Pullover Hoodie', 'Evening Sand', 'XS'),
('PENSHOPPE', 'Dress Code Relaxed Knit Sweater', 'Midnight Navy', 'L'),
('PENSHOPPE', 'Dress Code Comfort Slim Fit Pants', 'Dark Gray', 'XS'),
('PENSHOPPE', 'Essentials Classic Straight Fit Jeans', 'Light Blue', 'XXS'),
('PENSHOPPE', 'Slim Fit Ankle Length Chinos', 'Black', 'XS'),
('PENSHOPPE', 'Essentials Modern Fit Sports Shorts', 'Black', 'XS'),
('PENSHOPPE', 'Penshoppe Play Windbreaker Jacket', 'Black', 'XS'),
('PENSHOPPE', 'Penshoppe Play Windbreaker Jacket', 'Black', 'S'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'XS'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'S'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'M'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'L'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'XL'),
('H&M', '3-PACK REGULAR FIT T-SHIRTS', 'Black/Burgundy', 'XXL'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XS/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'S/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'M/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'L/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XL/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XXL/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', '3XL/L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XS'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'S'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'M'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'L'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XL'),
('H&M', 'SLIM FIT T-SHIRT', 'Black', 'XXL'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'XS'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'S'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'M'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'L'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'XL'),
('H&M', 'OVERSIZED FIT T-SHIRT', 'Yellow/Matisse', 'XXL'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'XS'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'S'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'M'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'L'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'XL'),
('H&M', 'REGULAR FIT T-SHIRT', 'Beige', 'XXL'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'XS'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'S'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'M'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'L'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'XL'),
('H&M', 'RELAXED-FIT T-SHIRT', 'Blue', 'XXL'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'XS'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'S'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'M'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'L'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'XL'),
('H&M', 'REGULAR-FIT CORDUROY SHIRT', 'Burgundy', 'XXL'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'XS'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'S'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'M'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'L'),
('H&M', 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 'Light blue/Rerun', 'XL'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'XS'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'S'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'M'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'L'),
('H&M', 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 'Light yellow/Vibrant', 'XL'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Navy blue', 'S'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Navy blue', 'M'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Navy blue', 'L'),
('H&M', 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 'Navy blue', 'XL'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'XS'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'S'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'M'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'L'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'XL'),
('H&M', 'COOLING REGULAR-FIT POLO SHIRT', 'Black', 'XXL'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'XS'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'S'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'M'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'L'),
('H&M', 'RELAXED-FIT POLO SHIRT', 'Black/Iron Maiden', 'XL'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'XS'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'S'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'M'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'L'),
('H&M', 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 'Black', 'XL'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'XS'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'S'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'M'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'L'),
('H&M', 'LOOSE-FIT COTTON POLO SHIRT', 'Light gray melange/striped', 'XL'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'XS'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'S'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'M'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'L'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'XL'),
('H&M', 'REGULAR-FIT POLO SHIRT', 'White', 'XXL'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'XS'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'S'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'M'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'L'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'XL'),
('H&M', 'RELAXED-FIT HOODIE', 'Dark gray', 'XXL'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'XS'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'S'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'M'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'L'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'XL'),
('H&M', 'RELAXED-FIT SWEATSHIRT', 'Navy blue', 'XXL'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'S'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'M'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'L'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'XL'),
('H&M', 'LOOSE-FIT SCUBA HOODIE', 'Dark brown', 'XXL'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'XS'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'S'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'M'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'L'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'XL'),
('H&M', 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 'Black', 'XXL'),
('H&M', 'RELAXED JEANS', 'Light denim blue', 'S'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'XS'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'S'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'M'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'L'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'XL'),
('H&M', 'REGULAR-FIT SWEATSHORTS', 'Light gray melange', 'XXL'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'XS'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'S'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'M'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'L'),
('H&M', 'WINDPROOF AND WATER-REPELLENT JACKET', 'Khaki green', 'XL'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'XS'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'S'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'M'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'L'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'XL'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'XXL'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', 'S'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', 'M'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', 'L'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', 'XL'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Light Bone', '2XL'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', 'S'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', 'M'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', 'L'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', 'XL'),
('Nike', 'Nike Sportswear Long-Sleeve T-Shirt', 'Anthracite', '2XL'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', 'S'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', 'M'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', 'L'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', 'XL'),
('Nike', 'Nike Sportswear T-Shirt', 'Fir', '2XL'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', 'S'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', 'M'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', 'L'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', 'XL'),
('Nike', 'Nike Sportswear T-Shirt', 'Tough Red', '2XL'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'XS'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'S'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'M'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'L'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', 'XL'),
('Nike', 'Nike Club Men''s Short-Sleeve Button-Up Shirt', 'Obsidian/White', '2XL'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', 'S'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', 'M'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', 'L'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', 'XL'),
('Nike', 'Nike 24.7 PerfectStretch Men''s Dri-FIT UV Button-Up Shirt', 'Light Orewood Brown/Sail/Light Iron Ore', '2XL'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'XS'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'S'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'M'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'L'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'XL'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'XXL'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'S'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'M'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'L'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'XL'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', '2XL'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'S'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'M'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'L'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'XL'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'XXL'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', 'S'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', 'M'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', 'L'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', 'XL'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple', '2XL'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'S'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'M'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'L'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'XL'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', '2XL');

INSERT INTO tmp_brand_sizes (brand, name, color, size, department) VALUES
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'XS', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'S', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'M', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'L', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', 'XL', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'Black', '2XL', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'XS', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'S', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'M', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'L', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', 'XL', 'women'),
('Nike', 'Nike Sportswear Women''s T-Shirt', 'White', '2XL', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'XS', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'S', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'M', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'L', 'women'),
('Nike', 'Nike Sportswear Women''s Loose T-Shirt', 'Black', 'XL', 'women'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'XS', 'kids'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'S', 'kids'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'M', 'kids'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'L', 'kids'),
('Nike', 'Nike Sportswear Kids'' T-Shirt', 'White', 'XL', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'XS', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'S', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'M', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'L', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'Black', 'XL', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'XS', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'S', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'M', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'L', 'kids'),
('Nike', 'Nike Sportswear Older Kids'' T-Shirt', 'White', 'XL', 'kids'),
('H&M', 'Oxford Shirt', 'White', 'XS', 'women'),
('H&M', 'Oxford Shirt', 'White', 'S', 'women'),
('H&M', 'Oxford Shirt', 'White', 'M', 'women'),
('H&M', 'Oxford Shirt', 'White', 'L', 'women'),
('H&M', 'Oxford Shirt', 'White', 'XL', 'women'),
('H&M', 'Oxford Shirt', 'Blue', 'XS', 'women'),
('H&M', 'Oxford Shirt', 'Blue', 'S', 'women'),
('H&M', 'Oxford Shirt', 'Blue', 'M', 'women'),
('H&M', 'Oxford Shirt', 'Blue', 'L', 'women'),
('H&M', 'Oxford Shirt', 'Blue', 'XL', 'women'),
('H&M', 'Oxford Shirt', 'Light Pink', 'XS', 'women'),
('H&M', 'Oxford Shirt', 'Light Pink', 'S', 'women'),
('H&M', 'Oxford Shirt', 'Light Pink', 'M', 'women'),
('H&M', 'Oxford Shirt', 'Light Pink', 'L', 'women'),
('H&M', 'Oxford Shirt', 'Light Pink', 'XL', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'XXS', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'XS', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'S', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'M', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'L', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'XL', 'women'),
('H&M', 'Knit T-Shirt', 'Beige melange', 'XXL', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'XXS', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'XS', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'S', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'M', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'L', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'XL', 'women'),
('H&M', 'Knit T-Shirt', 'Cream', 'XXL', 'women'),
('H&M', 'Cotton T-shirt', 'White', 'XS', 'kids'),
('H&M', 'Cotton T-shirt', 'White', 'S', 'kids'),
('H&M', 'Cotton T-shirt', 'White', 'M', 'kids'),
('H&M', 'Cotton T-shirt', 'White', 'L', 'kids'),
('H&M', 'Cotton T-shirt', 'White', 'XL', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Red', 'XS', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Red', 'S', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Red', 'M', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Red', 'L', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Red', 'XL', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'XS', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'S', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'M', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'L', 'kids'),
('H&M', 'Cotton T-shirt', 'Light Turqoise', 'XL', 'kids'),
('H&M', 'Cotton Jersey T-shirt', 'Cerise', 'S', 'kids'),
('H&M', 'Cotton Jersey T-shirt', 'Cerise', 'M', 'kids'),
('H&M', 'Cotton Jersey T-shirt', 'Cerise', 'L', 'kids'),
('H&M', 'Cotton Jersey T-shirt', 'Cerise', 'XL', 'kids'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'S', 'women'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'M', 'women'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'L', 'women'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'XL', 'women'),
('PENSHOPPE', 'Low Power Mode Slim Graphic T-Shirt', 'Off White', 'XXL', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'S', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'M', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'L', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'XL', 'women'),
('PENSHOPPE', 'Cherry Muse Slim Graphic T-Shirt', 'Black', 'XXL', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'S', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'M', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'L', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'XL', 'women'),
('PENSHOPPE', 'Essentials Relaxed Striped T-Shirt', 'Red', 'XXL', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'S', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'M', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'L', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'XL', 'women'),
('PENSHOPPE', 'Long Sleeve Striped Button-Down Top with Bows', 'Blush', 'XXL', 'women');

CREATE TEMPORARY TABLE tmp_missing_size_groups (
  brand VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  color VARCHAR(80) NOT NULL,
  size_group VARCHAR(20) NOT NULL,
  department VARCHAR(20) NOT NULL DEFAULT 'men'
);

INSERT INTO tmp_missing_size_groups (brand, name, color, size_group) VALUES
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

CREATE TEMPORARY TABLE tmp_missing_size_options (
  size_group VARCHAR(20) NOT NULL,
  size VARCHAR(20) NOT NULL
);

INSERT INTO tmp_missing_size_options (size_group, size) VALUES
('hm_alpha', 'XS'), ('hm_alpha', 'S'), ('hm_alpha', 'M'),
('hm_alpha', 'L'), ('hm_alpha', 'XL'), ('hm_alpha', 'XXL'),
('nike_alpha_5', 'S'), ('nike_alpha_5', 'M'), ('nike_alpha_5', 'L'),
('nike_alpha_5', 'XL'), ('nike_alpha_5', '2XL'),
('nike_alpha_6', 'XS'), ('nike_alpha_6', 'S'), ('nike_alpha_6', 'M'),
('nike_alpha_6', 'L'), ('nike_alpha_6', 'XL'), ('nike_alpha_6', '2XL');

INSERT INTO tmp_brand_sizes (brand, name, color, size, department)
SELECT missing.brand, missing.name, missing.color, options.size, missing.department
FROM tmp_missing_size_groups AS missing
JOIN tmp_missing_size_options AS options ON options.size_group = missing.size_group;

INSERT IGNORE INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, sizes.size, 5
FROM tmp_brand_sizes AS sizes
JOIN tmp_brand_products AS size_color
  ON size_color.brand = sizes.brand
 AND size_color.name = sizes.name
 AND size_color.color = sizes.color
 AND size_color.department = sizes.department
JOIN suppliers ON suppliers.name = sizes.brand
JOIN products AS p
  ON p.supplier_id = suppliers.supplier_id
 AND p.name = sizes.name
 AND p.department = sizes.department
JOIN tmp_brand_products AS product_color
  ON product_color.brand = sizes.brand
 AND product_color.name = sizes.name
 AND product_color.color = sizes.color
 AND product_color.department = sizes.department
JOIN product_colors AS pc
  ON pc.product_id = p.product_id
 AND pc.color = product_color.color;

UPDATE product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
JOIN tmp_brand_sizes AS sizes
  ON sizes.brand = s.name
 AND sizes.name = p.name
 AND sizes.department = p.department
 AND sizes.color = pc.color
 AND sizes.size = v.size
SET v.stock = 5;

DROP TEMPORARY TABLE tmp_brand_sizes;
DROP TEMPORARY TABLE tmp_brand_images;
DROP TEMPORARY TABLE tmp_brand_products;
DROP TEMPORARY TABLE tmp_missing_size_options;
DROP TEMPORARY TABLE tmp_missing_size_groups;

COMMIT;
