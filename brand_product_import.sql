-- Verified product listings and colorways from the sources documented in product_links.txt.
-- Additional Nike colorways are included where their official pages were accessible.
-- H&M and PENSHOPPE pages blocked follow-up checks, so their previously recorded colors remain.
-- Import into the existing soigne_apparel database after applying the main schema.
-- Images are linked to filenames in images/ when a matching image is available.
-- Unmatched or color-mismatched images remain NULL. Availability/stock is initialized to 0.
-- Product-page URLs and research limitations are documented in product_links.txt.

ALTER TABLE product_colors
  MODIFY color VARCHAR(80) NOT NULL;

START TRANSACTION;

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
  color VARCHAR(80) NOT NULL
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
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', 'Nike_Shorts_NMDF10_SPBB.jpg'),
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
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Shorts', 3295.00, 'Space Purple/Black/Black'),
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

INSERT INTO products (supplier_id, name, category, department, price, description)
SELECT suppliers.supplier_id, source.name, source.category, 'men', source.price, NULL
FROM (
  SELECT DISTINCT brand, name, category, price
  FROM tmp_brand_products
) AS source
JOIN suppliers ON suppliers.name = source.brand
WHERE NOT EXISTS (
  SELECT 1
  FROM products AS existing
  WHERE existing.supplier_id = suppliers.supplier_id
    AND existing.name = source.name
);

INSERT INTO product_colors (product_id, color, image)
SELECT DISTINCT p.product_id, source.color, images.image
FROM tmp_brand_products AS source
JOIN suppliers ON suppliers.name = source.brand
JOIN products AS p
  ON p.supplier_id = suppliers.supplier_id
 AND p.name = source.name
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
  size VARCHAR(20) NOT NULL
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
('PENSHOPPE', 'Essentials Classic Straight Fit Jeans', 'Light Blue', 'SIZE 28'),
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
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US XS'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US S'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US M'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US L'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US XL'),
('Nike', 'Jordan Sport Space Jam Men''s Crew-Neck T-Shirt', 'Black', 'US XXL'),
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
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US XS'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US S'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US M'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US L'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US XL'),
('Nike', 'Jordan Sport Men''s Golf Jacquard Polo', 'Jade Smoke/Jade Horizon/Jade Smoke', 'US XXL'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'S'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'M'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'L'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', 'XL'),
('Nike', 'Ja Men''s Basketball Hoodie', 'Black', '2XL'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'US S'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'US M'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'US L'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'US XL'),
('Nike', 'Paris Saint-Germain Men''s Fleece Trousers', 'Black', 'US XXL'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', 'S'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', 'M'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', 'L'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', 'XL'),
('Nike', 'Nike Men''s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 'Space Purple/Black/Black', '2XL'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'S'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'M'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'L'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', 'XL'),
('Nike', 'ACG Dolomiti Gilet', 'Black/Summit White', '2XL');

INSERT IGNORE INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, sizes.size, 0
FROM tmp_brand_sizes AS sizes
JOIN tmp_brand_products AS size_color
  ON size_color.brand = sizes.brand
 AND size_color.name = sizes.name
 AND size_color.color = sizes.color
JOIN suppliers ON suppliers.name = sizes.brand
JOIN products AS p
  ON p.supplier_id = suppliers.supplier_id
 AND p.name = sizes.name
JOIN tmp_brand_products AS product_color
  ON product_color.brand = sizes.brand
 AND product_color.name = sizes.name
JOIN product_colors AS pc
  ON pc.product_id = p.product_id
 AND pc.color = product_color.color;

UPDATE product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
SET v.stock = 10
WHERE s.name = 'Nike'
  AND p.name = 'Nike Sportswear Long-Sleeve T-Shirt'
  AND pc.color = 'Anthracite';

DROP TEMPORARY TABLE tmp_brand_sizes;
DROP TEMPORARY TABLE tmp_brand_images;
DROP TEMPORARY TABLE tmp_brand_products;

COMMIT;
