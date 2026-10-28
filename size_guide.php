<?php
session_start();

$page_title = "Size Guide | Soigné";
$extra_css = 'size-guide.css';
$dept = 'men';
$product_size_guide = require("includes/product_size_guide.php");

$size_ranges = [
    '25-27' => [
        'label' => $product_size_guide['bottom_ranges']['men']['25-27']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['men']['25-27']['sizes'],
        'waist' => ['64-67', '67-70', '70-73'],
        'low_hip' => ['84-86', '86-88', '88-90'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['75-77', '75-77', '76-78']],
            ['label' => '32" length cm', 'values' => ['79-81', '79-81', '80-82']],
            ['label' => '34" length cm', 'values' => ['83-85', '83-85', '84-86']],
        ],
    ],
    '28-30' => [
        'label' => $product_size_guide['bottom_ranges']['men']['28-30']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['men']['28-30']['sizes'],
        'waist' => ['73-76', '76-79', '79-82'],
        'low_hip' => ['90-92', '92-94', '94-96'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['76-78', '77-79', '77-79']],
            ['label' => '32" length cm', 'values' => ['80-82', '81-83', '81-83']],
            ['label' => '34" length cm', 'values' => ['84-86', '85-87', '85-87']],
        ],
    ],
    '31-33' => [
        'label' => $product_size_guide['bottom_ranges']['men']['31-33']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['men']['31-33']['sizes'],
        'waist' => ['82-85', '85-88', '88-91'],
        'low_hip' => ['96-98', '98-100', '100-102'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['78-80', '78-80', '79-81']],
            ['label' => '32" length cm', 'values' => ['82-84', '82-84', '83-85']],
            ['label' => '34" length cm', 'values' => ['86-88', '86-88', '87-89']],
        ],
    ],
    '34-38' => [
        'label' => $product_size_guide['bottom_ranges']['men']['34-38']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['men']['34-38']['sizes'],
        'waist' => ['91-94', '97-100', '103-106'],
        'low_hip' => ['102-104', '106-108', '110-112'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['79-81', '80-82', '81-83']],
            ['label' => '32" length cm', 'values' => ['83-85', '84-86', '85-87']],
            ['label' => '34" length cm', 'values' => ['87-89', '88-90', '89-91']],
        ],
    ],
    '40-44' => [
        'label' => $product_size_guide['bottom_ranges']['men']['40-44']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['men']['40-44']['sizes'],
        'waist' => ['109-112', '115-118', '121-124'],
        'low_hip' => ['114-116', '118-120', '122-124'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['82-84', '83-85', '84-86']],
            ['label' => '32" length cm', 'values' => ['86-88', '87-89', '88-90']],
            ['label' => '34" length cm', 'values' => ['90-92', '91-93', '92-94']],
        ],
    ],
];

$top_ranges = [
    'regular' => [
        'label' => 'Regular',
        'groups' => [
            'xxs-s' => [
                'label' => 'XXS-S',
                'sizes' => ['XXS', 'XS', 'S'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['38S', '40-42', '44-46']],
                    ['label' => 'US', 'values' => ['XXS', '30R-32R', '34R-36R']],
                ],
                'measurements' => [
                    'chest' => ['74-78', '78-86', '86-90'],
                    'waist' => ['62-66', '66-74', '74-78'],
                    'arm_length' => ['59', '59', '59-60'],
                    'neckline' => ['33', '34-35', '36'],
                ],
                'estimated' => false,
            ],
            'm-xl' => [
                'label' => 'M-XL',
                'sizes' => ['M', 'L', 'XL'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['48-50', '52', '54-56']],
                    ['label' => 'US', 'values' => ['38R-40R', '42R', '44R-46R']],
                ],
                'measurements' => [
                    'chest' => ['90-94', '94-98', '98-102'],
                    'waist' => ['78-82', '82-86', '86-90'],
                    'arm_length' => ['60-61', '61-62', '62-63'],
                    'neckline' => ['38', '39-40', '41'],
                ],
                'estimated' => true,
            ],
            'xxl-3xl' => [
                'label' => 'XXL-3XL',
                'sizes' => ['XXL', '3XL'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['56-58', '62-64']],
                    ['label' => 'US', 'values' => ['46R-48R', '52R-54R']],
                ],
                'measurements' => [
                    'chest' => ['102-106', '110-114'],
                    'waist' => ['90-94', '98-102'],
                    'arm_length' => ['63-64', '65-66'],
                    'neckline' => ['42', '45'],
                ],
                'estimated' => true,
            ],
        ],
    ],
    'long' => [
        'label' => 'Long',
        'groups' => [
            'xs-m' => [
                'label' => 'XS/L-M/L',
                'sizes' => ['XS/L', 'S/L', 'M/L'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['40L-42L', '44L-46L', '48L-50L']],
                    ['label' => 'US', 'values' => ['30L-32L', '34L-36L', '38L-40L']],
                ],
                'measurements' => [
                    'chest' => ['78-86', '86-94', '94-102'],
                    'waist' => ['66-74', '74-82', '82-90'],
                    'arm_length' => ['62', '62-63', '63-64'],
                    'neckline' => ['34-35', '36-37', '38-39'],
                ],
                'estimated' => false,
            ],
            'l-xxl' => [
                'label' => 'L/L-XXL/L',
                'sizes' => ['L/L', 'XL/L', 'XXL/L'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['52L', '54L-56L', '58L-60L']],
                    ['label' => 'US', 'values' => ['42L', '44L-46L', '48L-50L']],
                ],
                'measurements' => [
                    'chest' => ['98-102', '102-110', '110-118'],
                    'waist' => ['86-90', '90-98', '98-106'],
                    'arm_length' => ['64-65', '65-66', '66-67'],
                    'neckline' => ['40-41', '42-43', '44-45'],
                ],
                'estimated' => true,
            ],
            '3xl' => [
                'label' => '3XL/L',
                'sizes' => ['3XL/L'],
                'size_reference' => [
                    ['label' => 'EUR', 'values' => ['62L-64L']],
                    ['label' => 'US', 'values' => ['52L-54L']],
                ],
                'measurements' => [
                    'chest' => ['118-126'],
                    'waist' => ['106-114'],
                    'arm_length' => ['67-68'],
                    'neckline' => ['46-47'],
                ],
                'estimated' => true,
            ],
        ],
    ],
];

$women_top_ranges = [
    'xxs-s' => [
        'label' => 'XXS-S',
        'sizes' => ['XXS', 'XS', 'S'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['32', '34', '36-38']],
            ['label' => 'US', 'values' => ['0', '2', '4-6']],
        ],
        'measurements' => [
            'chest' => ['74-78', '78-82', '82-90'],
            'waist' => ['58-62', '62-66', '66-74'],
            'arm_length' => ['56', '56', '57'],
            'low_hip' => ['79-83', '83-87', '87-94.5'],
        ],
        'estimated' => false,
    ],
    'm-xl' => [
        'label' => 'M-XL',
        'sizes' => ['M', 'L', 'XL'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['40', '42', '44-46']],
            ['label' => 'US', 'values' => ['8', '10', '12-14']],
        ],
        'measurements' => [
            'chest' => ['90-94', '94-102', '102-110'],
            'waist' => ['74-78', '78-86', '86-94'],
            'arm_length' => ['58', '59', '60'],
            'low_hip' => ['94.5-98.5', '98.5-106.5', '106.5-114.5'],
        ],
        'estimated' => true,
    ],
    'xxl-4xl' => [
        'label' => 'XXL-4XL',
        'sizes' => ['XXL', '3XL', '4XL'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['48-50', '52-54', '56-58']],
            ['label' => 'US', 'values' => ['16-18', '20-22', '24-26']],
        ],
        'measurements' => [
            'chest' => ['110-118', '118-126', '126-134'],
            'waist' => ['94-102', '102-110', '110-118'],
            'arm_length' => ['61', '62', '63'],
            'low_hip' => ['114.5-122.5', '122.5-130.5', '130.5-138.5'],
        ],
        'estimated' => true,
    ],
];

$women_bottom_ranges = [
    '32-36' => [
        'label' => $product_size_guide['bottom_ranges']['women']['32-36']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['32-36']['sizes'],
        'measurements' => [
            'waist' => ['58-62', '62-66', '66-70'],
            'low_hip' => ['79-83', '83-87', '87-91'],
            'inside_leg' => ['74.5', '74.5', '74'],
        ],
        'estimated' => false,
    ],
    '38-42' => [
        'label' => $product_size_guide['bottom_ranges']['women']['38-42']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['38-42']['sizes'],
        'measurements' => [
            'waist' => ['70-74', '74-78', '78-82'],
            'low_hip' => ['91-95', '95-99', '99-103'],
            'inside_leg' => ['73.5', '73', '72.5'],
        ],
        'estimated' => true,
    ],
    '44-48' => [
        'label' => $product_size_guide['bottom_ranges']['women']['44-48']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['44-48']['sizes'],
        'measurements' => [
            'waist' => ['82-86', '86-90', '90-94'],
            'low_hip' => ['103-107', '107-111', '111-115'],
            'inside_leg' => ['72', '71.5', '71'],
        ],
        'estimated' => true,
    ],
    '50-54' => [
        'label' => $product_size_guide['bottom_ranges']['women']['50-54']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['50-54']['sizes'],
        'measurements' => [
            'waist' => ['94-100', '100-106', '106-112'],
            'low_hip' => ['115-121', '121-127', '127-133'],
            'inside_leg' => ['70.5', '70', '69.5'],
        ],
        'estimated' => true,
    ],
    '56-60' => [
        'label' => $product_size_guide['bottom_ranges']['women']['56-60']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['56-60']['sizes'],
        'measurements' => [
            'waist' => ['112-118', '118-124', '124-130'],
            'low_hip' => ['133-139', '139-145', '145-151'],
            'inside_leg' => ['69', '68.5', '68'],
        ],
        'estimated' => true,
    ],
    '62' => [
        'label' => $product_size_guide['bottom_ranges']['women']['62']['label'],
        'sizes' => $product_size_guide['bottom_ranges']['women']['62']['sizes'],
        'measurements' => [
            'waist' => ['130-136'],
            'low_hip' => ['151-157'],
            'inside_leg' => ['67.5'],
        ],
        'estimated' => true,
    ],
];

$kids_top_guide = [
    'sizes' => ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
    'size_ranges' => [
        'xxs-s' => ['label' => 'XXS-S', 'indexes' => [0, 1, 2]],
        'm-xl' => ['label' => 'M-XL', 'indexes' => [3, 4, 5]],
        'xxl-3xl' => ['label' => 'XXL-3XL', 'indexes' => [6, 7]],
    ],
    'measurements' => [
        ['label' => 'Numeric size', 'values' => ['6-7', '8-9', '8-9 Plus', '10-12', '14-16', '10-12 Plus', '18-20', '14-16 Plus']],
        ['label' => 'Age', 'values' => ['7-8', '8-10', '8-10', '10-12', '12-13', '10-12', '13-15', '12-13']],
        ['label' => 'Height (cm)', 'values' => ['122-128', '128-137', '131-140', '137-147', '147-158', '140-150', '158-170', '150-160']],
        ['label' => 'Chest (cm)', 'values' => ['61.5-65', '65-70', '70-78', '70-77', '77-84', '78-86', '84-91.5', '86-94']],
        ['label' => 'Waist (cm)', 'values' => ['50-56', '56-62', '61-67', '62-68', '68-74', '67-73', '74-80', '73-79']],
        ['label' => 'Hip (cm)', 'values' => ['63-67', '67-74', '78-82', '74-80', '80-88', '82-88', '88-96', '88-95']],
    ],
];

$women_top_measurements = [
    'chest' => 'Chest',
    'waist' => 'Waist',
    'arm_length' => 'Arm length',
    'low_hip' => 'Low hip',
];

include_once('includes/header.php');
?>

<main class="size-guide-page">
  <header class="size-guide-heading">
    <h1>Size Guide</h1>
  </header>

  <nav class="department-guide-tabs" aria-label="Size guide departments">
    <a href="#men-size-guide">Men</a>
    <a href="#women-size-guide">Women</a>
    <a href="#kids-size-guide">Kids</a>
  </nav>

  <section class="size-guide-card" id="men-size-guide" aria-label="Men's size guide">

    <div class="top-guide bottom-guide" id="top-guide">
      <h2>Men's tops</h2>

      <?php foreach ($top_ranges as $fit_key => $fit) { ?>
        <section class="top-fit-panel">
          <h3><?php echo htmlspecialchars($fit['label']); ?> fit</h3>
          <div class="measurement-accordions">
            <?php
            $top_measurements = [
                'chest' => 'Chest',
                'waist' => 'Waist',
                'arm_length' => 'Arm length',
                'neckline' => 'Neckline',
            ];
            ?>
            <?php foreach ($top_measurements as $measurement_key => $measurement_label) { ?>
              <?php $panel_id = 'top-' . $fit_key . '-' . str_replace('_', '-', $measurement_key) . '-panel'; ?>
              <section class="measurement-section">
                <h4><?php echo htmlspecialchars($measurement_label); ?></h4>
                <div class="measurement-panel" id="<?php echo $panel_id; ?>">
                  <div class="measurement-table-wrap">
                    <?php foreach ($fit['groups'] as $group) { ?>
                      <table class="measurement-table">
                        <caption><?php echo htmlspecialchars($measurement_label . ' · ' . $group['label']); ?></caption>
                        <thead><tr><th scope="col">Size</th><?php foreach ($group['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                        <tbody><tr><th scope="row"><?php echo htmlspecialchars($measurement_label); ?> (cm)</th><?php foreach ($group['measurements'][$measurement_key] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr></tbody>
                      </table>
                    <?php } ?>
                  </div>
                </div>
              </section>
            <?php } ?>
          </div>
        </section>
      <?php } ?>
    </div>

    <div class="bottom-guide" id="bottom-guide">
      <h2>Men's bottoms</h2>

      <div class="measurement-accordions">
        <section class="measurement-section">
          <h3>Waist</h3>
          <div class="measurement-panel" id="waist-panel">
            <div class="measurement-table-wrap">
              <?php foreach ($size_ranges as $range) { ?>
              <table class="measurement-table">
                <caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption>
                <thead><tr><th scope="col"><?php echo htmlspecialchars($range['label']); ?> · Size</th><?php foreach ($range['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                <tbody><tr><th scope="row">Waist (cm)</th><?php foreach ($range['waist'] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr></tbody>
              </table>
              <?php } ?>
            </div>
          </div>
        </section>

        <section class="measurement-section">
          <h3>Low hip</h3>
          <div class="measurement-panel" id="low-hip-panel">
            <div class="measurement-table-wrap">
              <?php foreach ($size_ranges as $range) { ?>
              <table class="measurement-table">
                <caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption>
                <thead><tr><th scope="col"><?php echo htmlspecialchars($range['label']); ?> · Size</th><?php foreach ($range['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                <tbody><tr><th scope="row">Low hip (cm)</th><?php foreach ($range['low_hip'] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr></tbody>
              </table>
              <?php } ?>
            </div>
          </div>
        </section>

        <section class="measurement-section">
          <h3>Inside leg length</h3>
          <div class="measurement-panel" id="inside-leg-panel">
            <div class="measurement-table-wrap">
              <?php foreach ($size_ranges as $range) { ?>
              <table class="measurement-table">
                <caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption>
                <thead><tr><th scope="col"><?php echo htmlspecialchars($range['label']); ?> · Size</th><?php foreach ($range['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                <tbody><?php foreach ($range['inside_leg'] as $measurement_row) { ?><tr><th scope="row"><?php echo htmlspecialchars($measurement_row['label']); ?></th><?php foreach ($measurement_row['values'] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr><?php } ?></tbody>
              </table>
              <?php } ?>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>

  <section class="size-guide-card women-size-guide" id="women-size-guide" aria-label="Women's size guide">
    <div class="bottom-guide" id="women-top-guide">
      <h2>Women's tops</h2>
      <p class="women-fit-label">Regular</p>

      <div class="measurement-accordions">
        <?php foreach ($women_top_measurements as $measurement_key => $measurement_label) { ?>
          <?php $panel_id = 'women-' . str_replace('_', '-', $measurement_key) . '-panel'; ?>
          <section class="measurement-section">
            <h3>
              <?php echo htmlspecialchars($measurement_label); ?>
            </h3>
            <div class="measurement-panel" id="<?php echo $panel_id; ?>">
              <div class="measurement-table-wrap">
                <?php foreach ($women_top_ranges as $range) { ?>
                  <table class="measurement-table">
                    <caption><?php echo htmlspecialchars($measurement_label . ' · ' . $range['label']); ?></caption>
                    <thead><tr><th scope="col">Size</th><?php foreach ($range['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                    <tbody><tr><th scope="row"><?php echo htmlspecialchars($measurement_label); ?> (cm)</th><?php foreach ($range['measurements'][$measurement_key] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr></tbody>
                  </table>
                <?php } ?>
              </div>
            </div>
          </section>
        <?php } ?>
      </div>
    </div>

    <div class="bottom-guide" id="women-bottom-guide">
      <h2>Women's bottoms</h2>
      <p class="women-fit-label">Regular</p>

      <div class="measurement-accordions">
        <?php
        $women_bottom_measurements = [
            'waist' => 'Waist',
            'low_hip' => 'Low hip',
            'inside_leg' => 'Inside leg length',
        ];
        ?>
        <?php foreach ($women_bottom_measurements as $measurement_key => $measurement_label) { ?>
          <?php $panel_id = 'women-bottom-' . str_replace('_', '-', $measurement_key) . '-panel'; ?>
          <section class="measurement-section">
            <h3>
              <?php echo htmlspecialchars($measurement_label); ?>
            </h3>
            <div class="measurement-panel" id="<?php echo $panel_id; ?>">
              <div class="measurement-table-wrap">
                <?php foreach ($women_bottom_ranges as $range) { ?>
                  <table class="measurement-table">
                    <caption><?php echo htmlspecialchars($measurement_label . ' · ' . $range['label']); ?></caption>
                    <thead><tr><th scope="col">Size</th><?php foreach ($range['sizes'] as $size) { ?><th scope="col"><?php echo htmlspecialchars($size); ?></th><?php } ?></tr></thead>
                    <tbody><tr><th scope="row"><?php echo htmlspecialchars($measurement_label); ?><?php echo $measurement_key === 'inside_leg' ? ' (cm)' : ' (cm)'; ?></th><?php foreach ($range['measurements'][$measurement_key] as $value) { ?><td><?php echo htmlspecialchars($value); ?></td><?php } ?></tr></tbody>
                  </table>
                <?php } ?>
              </div>
            </div>
          </section>
        <?php } ?>
      </div>
    </div>
  </section>

  <section class="size-guide-card kids-size-guide" id="kids-size-guide" aria-label="Kids' size guide">
    <div class="bottom-guide">
      <h2>Top</h2>
      <div class="measurement-table-wrap kids-basic-table-wrap">
        <?php foreach ($kids_top_guide['size_ranges'] as $range) { ?>
        <table class="measurement-table kids-basic-table">
          <caption>Kids' top sizes and measurements · <?php echo htmlspecialchars($range['label']); ?></caption>
          <thead>
            <tr>
              <th class="measurement-label" scope="col">Measurement</th>
              <?php foreach ($range['indexes'] as $size_index) { ?>
                <th scope="col"><?php echo htmlspecialchars($kids_top_guide['sizes'][$size_index]); ?></th>
              <?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($kids_top_guide['measurements'] as $measurement) { ?>
              <tr>
                <th class="measurement-label" scope="row"><?php echo htmlspecialchars($measurement['label']); ?></th>
                <?php foreach ($range['indexes'] as $size_index) { ?>
                  <td><?php echo htmlspecialchars($measurement['values'][$size_index]); ?></td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
        <?php } ?>
      </div>
    </div>
  </section>
</main>

<?php include_once('includes/footer.php'); ?>
