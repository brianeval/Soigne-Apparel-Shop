<?php
session_start();

$page_title = "Size Guide | Soigné";
$extra_css = 'size-guide.css';
$dept = 'men';

$size_ranges = [
    '25-27' => [
        'sizes' => ['25', '26', '27'],
        'waist' => ['64-67', '67-70', '70-73'],
        'low_hip' => ['84-86', '86-88', '88-90'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['75-77', '75-77', '76-78']],
            ['label' => '32" length cm', 'values' => ['79-81', '79-81', '80-82']],
            ['label' => '34" length cm', 'values' => ['83-85', '83-85', '84-86']],
        ],
    ],
    '28-30' => [
        'sizes' => ['28', '29', '30'],
        'waist' => ['73-76', '76-79', '79-82'],
        'low_hip' => ['90-92', '92-94', '94-96'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['76-78', '77-79', '77-79']],
            ['label' => '32" length cm', 'values' => ['80-82', '81-83', '81-83']],
            ['label' => '34" length cm', 'values' => ['84-86', '85-87', '85-87']],
        ],
    ],
    '31-33' => [
        'sizes' => ['31', '32', '33'],
        'waist' => ['82-85', '85-88', '88-91'],
        'low_hip' => ['96-98', '98-100', '100-102'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['78-80', '78-80', '79-81']],
            ['label' => '32" length cm', 'values' => ['82-84', '82-84', '83-85']],
            ['label' => '34" length cm', 'values' => ['86-88', '86-88', '87-89']],
        ],
    ],
    '34-38' => [
        'sizes' => ['34', '36', '38'],
        'waist' => ['91-94', '97-100', '103-106'],
        'low_hip' => ['102-104', '106-108', '110-112'],
        'inside_leg' => [
            ['label' => '30" length cm', 'values' => ['79-81', '80-82', '81-83']],
            ['label' => '32" length cm', 'values' => ['83-85', '84-86', '85-87']],
            ['label' => '34" length cm', 'values' => ['87-89', '88-90', '89-91']],
        ],
    ],
    '40-44' => [
        'sizes' => ['40', '42', '44'],
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
                    ['label' => 'US', 'values' => ['28R', '30R-32R', '34R-36R']],
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
        'label' => '32-36',
        'sizes' => ['32', '34', '36'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['32', '34', '36']],
            ['label' => 'US', 'values' => ['0', '2', '4']],
        ],
        'measurements' => [
            'waist' => ['58-62', '62-66', '66-70'],
            'low_hip' => ['79-83', '83-87', '87-91'],
            'inside_leg' => ['74.5', '74.5', '74'],
        ],
        'estimated' => false,
    ],
    '38-42' => [
        'label' => '38-42',
        'sizes' => ['38', '40', '42'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['38', '40', '42']],
            ['label' => 'US', 'values' => ['6', '8', '10']],
        ],
        'measurements' => [
            'waist' => ['70-74', '74-78', '78-82'],
            'low_hip' => ['91-95', '95-99', '99-103'],
            'inside_leg' => ['73.5', '73', '72.5'],
        ],
        'estimated' => true,
    ],
    '44-48' => [
        'label' => '44-48',
        'sizes' => ['44', '46', '48'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['44', '46', '48']],
            ['label' => 'US', 'values' => ['12', '14', '16']],
        ],
        'measurements' => [
            'waist' => ['82-86', '86-90', '90-94'],
            'low_hip' => ['103-107', '107-111', '111-115'],
            'inside_leg' => ['72', '71.5', '71'],
        ],
        'estimated' => true,
    ],
    '50-54' => [
        'label' => '50-54',
        'sizes' => ['50', '52', '54'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['50', '52', '54']],
            ['label' => 'US', 'values' => ['18', '20', '22']],
        ],
        'measurements' => [
            'waist' => ['94-100', '100-106', '106-112'],
            'low_hip' => ['115-121', '121-127', '127-133'],
            'inside_leg' => ['70.5', '70', '69.5'],
        ],
        'estimated' => true,
    ],
    '56-60' => [
        'label' => '56-60',
        'sizes' => ['56', '58', '60'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['56', '58', '60']],
            ['label' => 'US', 'values' => ['24', '26', '28']],
        ],
        'measurements' => [
            'waist' => ['112-118', '118-124', '124-130'],
            'low_hip' => ['133-139', '139-145', '145-151'],
            'inside_leg' => ['69', '68.5', '68'],
        ],
        'estimated' => true,
    ],
    '62' => [
        'label' => '62',
        'sizes' => ['62'],
        'size_reference' => [
            ['label' => 'EUR', 'values' => ['62']],
            ['label' => 'US', 'values' => ['30']],
        ],
        'measurements' => [
            'waist' => ['130-136'],
            'low_hip' => ['151-157'],
            'inside_leg' => ['67.5'],
        ],
        'estimated' => true,
    ],
];

$kids_age_guides = [
    '3-8y' => [
        'label' => '3-8Y',
        'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
        'measurements' => [
            ['label' => 'Height (cm)', 'values' => ['96-104', '104-110', '110-116', '116-122', '122-128']],
            ['label' => 'Age', 'values' => ['3-4Y', '4-5Y', '5-6Y', '6-7Y', '7-8Y']],
            ['label' => 'Chest (cm)', 'values' => ['53-56', '56-59', '59-61', '61-63', '63-66']],
            ['label' => 'Waist (cm)', 'values' => ['54-56', '56-58', '58-59', '59-60', '60-61']],
            ['label' => 'Low hip (cm)', 'values' => ['56-59', '59-61', '61-63', '63-66', '66-70']],
            ['label' => 'Inside leg (cm), estimated', 'values' => ['43-46', '46-49', '49-52', '52-56', '56-60']],
        ],
    ],
    '7-15y' => [
        'label' => '7-15Y',
        'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
        'measurements' => [
            ['label' => 'Height (cm)', 'values' => ['122-128', '128-137', '137-147', '147-158', '158-170']],
            ['label' => 'Age', 'values' => ['7-8Y', '8-10Y', '10-12Y', '12-13Y', '13-15Y']],
            ['label' => 'Chest (cm)', 'values' => ['61.5-65', '65-70', '70-77', '77-84', '84-91.5']],
            ['label' => 'Waist (cm)', 'values' => ['50-56', '56-62', '62-68', '68-74', '74-80']],
            ['label' => 'Low hip (cm)', 'values' => ['63-67', '67-74', '74-80', '80-88', '88-96']],
            ['label' => 'Inside leg (cm), estimated', 'values' => ['56-60', '60-65', '65-70', '70-75', '75-80']],
        ],
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
    <h1 id="guide-department-title">Men</h1>
  </header>

  <div class="department-guide-tabs" role="tablist" aria-label="Size guide department">
    <button type="button" class="department-guide-tab is-selected" id="men-size-tab" role="tab" aria-selected="true" aria-controls="men-size-guide" data-department-tab="men">Men</button>
    <button type="button" class="department-guide-tab" id="women-size-tab" role="tab" aria-selected="false" aria-controls="women-size-guide" data-department-tab="women">Women</button>
    <button type="button" class="department-guide-tab" id="kids-size-tab" role="tab" aria-selected="false" aria-controls="kids-size-guide" data-department-tab="kids">Kids</button>
  </div>

  <section class="size-guide-card" id="men-size-guide" role="tabpanel" aria-labelledby="men-size-tab" aria-label="Men's size guide" data-department-panel="men">
    <div class="guide-type-tabs" role="tablist" aria-label="Men's size guide type">
      <button type="button" class="guide-type-tab is-active" id="top-tab" role="tab" aria-selected="true" aria-controls="top-guide" data-guide-type="top">
        Top
      </button>
      <button type="button" class="guide-type-tab" id="bottom-tab" role="tab" aria-selected="false" aria-controls="bottom-guide" data-guide-type="bottom">
        Bottom
      </button>
    </div>

    <div class="top-guide bottom-guide" id="top-guide" role="tabpanel" aria-labelledby="top-tab" data-guide-panel="top">
      <h2 id="top-guide-title">Top</h2>

      <div class="top-fit-tabs" role="tablist" aria-label="Top fit">
        <?php foreach ($top_ranges as $fit_key => $fit) { ?>
          <button
            type="button"
            class="top-fit-tab<?php echo $fit_key === 'regular' ? ' is-selected' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $fit_key === 'regular' ? 'true' : 'false'; ?>"
            data-top-fit="<?php echo htmlspecialchars($fit_key); ?>">
            <?php echo htmlspecialchars($fit['label']); ?>
          </button>
        <?php } ?>
      </div>

      <?php foreach ($top_ranges as $fit_key => $fit) { ?>
        <section class="top-fit-panel" data-top-fit-panel="<?php echo htmlspecialchars($fit_key); ?>"<?php echo $fit_key === 'regular' ? '' : ' hidden'; ?>>
          <fieldset class="size-range-fieldset">
            <legend>Select size range</legend>
            <div class="size-range-options">
              <?php foreach ($fit['groups'] as $group_key => $group) { ?>
                <button
                  type="button"
                  class="size-range-option<?php echo $group_key === array_key_first($fit['groups']) ? ' is-selected' : ''; ?>"
                  data-top-range="<?php echo htmlspecialchars($fit_key . ':' . $group_key); ?>"
                  aria-pressed="<?php echo $group_key === array_key_first($fit['groups']) ? 'true' : 'false'; ?>">
                  <?php echo htmlspecialchars($group['label']); ?>
                </button>
              <?php } ?>
            </div>
          </fieldset>

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
                <h3>
                  <button type="button" class="measurement-toggle top-measurement-toggle" aria-expanded="<?php echo $measurement_key === 'chest' ? 'true' : 'false'; ?>" aria-controls="<?php echo $panel_id; ?>">
                    <?php echo htmlspecialchars($measurement_label); ?> <span class="toggle-mark" aria-hidden="true"></span>
                  </button>
                </h3>
                <div class="measurement-panel" id="<?php echo $panel_id; ?>"<?php echo $measurement_key === 'chest' ? '' : ' hidden'; ?>>
                  <div class="measurement-table-wrap">
                    <table class="measurement-table top-measurement-table" data-top-measurement="<?php echo htmlspecialchars($measurement_key); ?>">
                      <caption class="visually-hidden"><?php echo htmlspecialchars($measurement_label); ?> measurements by clothing size</caption>
                      <thead></thead><tbody></tbody>
                    </table>
                  </div>
                </div>
              </section>
            <?php } ?>
          </div>
        </section>
      <?php } ?>
    </div>

    <div class="bottom-guide" id="bottom-guide" role="tabpanel" aria-labelledby="bottom-tab" data-guide-panel="bottom" hidden>
      <h2 id="bottom-guide-title">Bottom</h2>

      <fieldset class="size-range-fieldset">
        <legend>Select size range</legend>
        <div class="size-range-options">
          <?php foreach (array_keys($size_ranges) as $index => $range) { ?>
            <?php $range_parts = explode('-', $range); ?>
            <button
              type="button"
              class="size-range-option<?php echo $index === 0 ? ' is-selected' : ''; ?>"
              data-size-range="<?php echo htmlspecialchars($range); ?>"
              aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>">
              <?php echo htmlspecialchars($range_parts[0]); ?>&quot;-<?php echo htmlspecialchars($range_parts[1]); ?>&quot;
            </button>
          <?php } ?>
        </div>
      </fieldset>

      <div class="measurement-accordions">
        <section class="measurement-section">
          <h3>
            <button type="button" class="measurement-toggle" aria-expanded="true" aria-controls="waist-panel">
              Waist <span class="toggle-mark" aria-hidden="true"></span>
            </button>
          </h3>
          <div class="measurement-panel" id="waist-panel">
            <div class="measurement-table-wrap">
              <table class="measurement-table" data-measurement="waist">
                <caption class="visually-hidden">Waist measurements in centimeters by waist size in inches</caption>
                <thead></thead><tbody></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="measurement-section">
          <h3>
            <button type="button" class="measurement-toggle" aria-expanded="false" aria-controls="low-hip-panel">
              Low hip <span class="toggle-mark" aria-hidden="true"></span>
            </button>
          </h3>
          <div class="measurement-panel" id="low-hip-panel" hidden>
            <div class="measurement-table-wrap">
              <table class="measurement-table" data-measurement="low_hip">
                <caption class="visually-hidden">Low hip measurements in centimeters by waist size in inches</caption>
                <thead></thead><tbody></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="measurement-section">
          <h3>
            <button type="button" class="measurement-toggle" aria-expanded="false" aria-controls="inside-leg-panel">
              Inside leg length <span class="toggle-mark" aria-hidden="true"></span>
            </button>
          </h3>
          <div class="measurement-panel" id="inside-leg-panel" hidden>
            <div class="measurement-table-wrap">
              <table class="measurement-table" data-measurement="inside_leg">
                <caption class="visually-hidden">Inside leg length measurements in centimeters by waist size in inches</caption>
                <thead></thead><tbody></tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>

  <section class="size-guide-card women-size-guide" id="women-size-guide" role="tabpanel" aria-labelledby="women-size-tab" aria-label="Women's size guide" data-department-panel="women" hidden>
    <div class="guide-type-tabs" role="tablist" aria-label="Women's size guide type">
      <button type="button" class="guide-type-tab is-active" id="women-top-tab" role="tab" aria-selected="true" aria-controls="women-top-guide" data-women-guide-type="top">Top</button>
      <button type="button" class="guide-type-tab" id="women-bottom-tab" role="tab" aria-selected="false" aria-controls="women-bottom-guide" data-women-guide-type="bottom">Bottom</button>
    </div>

    <div class="bottom-guide" id="women-top-guide" role="tabpanel" aria-labelledby="women-top-tab" data-women-guide-panel="top">
      <h2>Top</h2>
      <p class="women-fit-label">Regular</p>

      <fieldset class="size-range-fieldset">
        <legend>Select size range</legend>
        <div class="size-range-options">
          <?php foreach ($women_top_ranges as $range_key => $range) { ?>
            <button
              type="button"
              class="size-range-option<?php echo $range_key === array_key_first($women_top_ranges) ? ' is-selected' : ''; ?>"
              data-women-range="<?php echo htmlspecialchars($range_key); ?>"
              aria-pressed="<?php echo $range_key === array_key_first($women_top_ranges) ? 'true' : 'false'; ?>">
              <?php echo htmlspecialchars($range['label']); ?>
            </button>
          <?php } ?>
        </div>
      </fieldset>

      <div class="measurement-accordions">
        <?php foreach ($women_top_measurements as $measurement_key => $measurement_label) { ?>
          <?php $panel_id = 'women-' . str_replace('_', '-', $measurement_key) . '-panel'; ?>
          <section class="measurement-section">
            <h3>
              <button type="button" class="measurement-toggle women-measurement-toggle" aria-expanded="<?php echo $measurement_key === 'chest' ? 'true' : 'false'; ?>" aria-controls="<?php echo $panel_id; ?>">
                <?php echo htmlspecialchars($measurement_label); ?> <span class="toggle-mark" aria-hidden="true"></span>
              </button>
            </h3>
            <div class="measurement-panel" id="<?php echo $panel_id; ?>"<?php echo $measurement_key === 'chest' ? '' : ' hidden'; ?>>
              <div class="measurement-table-wrap">
                <table class="measurement-table women-measurement-table" data-women-measurement="<?php echo htmlspecialchars($measurement_key); ?>">
                  <caption class="visually-hidden"><?php echo htmlspecialchars($measurement_label); ?> measurements by clothing size</caption>
                  <thead></thead><tbody></tbody>
                </table>
              </div>
            </div>
          </section>
        <?php } ?>
      </div>
    </div>

    <div class="bottom-guide" id="women-bottom-guide" role="tabpanel" aria-labelledby="women-bottom-tab" data-women-guide-panel="bottom" hidden>
      <h2>Bottom</h2>
      <p class="women-fit-label">Regular</p>

      <fieldset class="size-range-fieldset">
        <legend>Select size range</legend>
        <div class="size-range-options women-bottom-range-options">
          <?php foreach ($women_bottom_ranges as $range_key => $range) { ?>
            <button
              type="button"
              class="size-range-option<?php echo $range_key === array_key_first($women_bottom_ranges) ? ' is-selected' : ''; ?>"
              data-women-bottom-range="<?php echo htmlspecialchars($range_key); ?>"
              aria-pressed="<?php echo $range_key === array_key_first($women_bottom_ranges) ? 'true' : 'false'; ?>">
              <?php echo htmlspecialchars($range['label']); ?>
            </button>
          <?php } ?>
        </div>
      </fieldset>

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
              <button type="button" class="measurement-toggle women-bottom-measurement-toggle" aria-expanded="<?php echo $measurement_key === 'waist' ? 'true' : 'false'; ?>" aria-controls="<?php echo $panel_id; ?>">
                <?php echo htmlspecialchars($measurement_label); ?> <span class="toggle-mark" aria-hidden="true"></span>
              </button>
            </h3>
            <div class="measurement-panel" id="<?php echo $panel_id; ?>"<?php echo $measurement_key === 'waist' ? '' : ' hidden'; ?>>
              <div class="measurement-table-wrap">
                <table class="measurement-table women-bottom-measurement-table" data-women-bottom-measurement="<?php echo htmlspecialchars($measurement_key); ?>">
                  <caption class="visually-hidden"><?php echo htmlspecialchars($measurement_label); ?> measurements by European and US size</caption>
                  <thead></thead><tbody></tbody>
                </table>
              </div>
            </div>
          </section>
        <?php } ?>
      </div>
    </div>
  </section>

  <section class="size-guide-card kids-size-guide" id="kids-size-guide" role="tabpanel" aria-labelledby="kids-size-tab" aria-label="Kids' size guide" data-department-panel="kids" hidden>
    <div class="bottom-guide">
      <h2>Top</h2>

      <div class="top-fit-tabs kids-age-tabs" role="tablist" aria-label="Kids' age">
        <?php foreach ($kids_age_guides as $age_key => $age_guide) { ?>
          <button
            type="button"
            class="top-fit-tab<?php echo $age_key === array_key_first($kids_age_guides) ? ' is-selected' : ''; ?>"
            id="kids-age-tab-<?php echo htmlspecialchars($age_key); ?>"
            role="tab"
            aria-selected="<?php echo $age_key === array_key_first($kids_age_guides) ? 'true' : 'false'; ?>"
            aria-controls="kids-age-panel-<?php echo htmlspecialchars($age_key); ?>"
            data-kids-age="<?php echo htmlspecialchars($age_key); ?>">
            <?php echo htmlspecialchars($age_guide['label']); ?>
          </button>
        <?php } ?>
      </div>

      <?php foreach ($kids_age_guides as $age_key => $age_guide) { ?>
        <section
          class="kids-age-panel"
          id="kids-age-panel-<?php echo htmlspecialchars($age_key); ?>"
          role="tabpanel"
          aria-labelledby="kids-age-tab-<?php echo htmlspecialchars($age_key); ?>"
          data-kids-age-panel="<?php echo htmlspecialchars($age_key); ?>"<?php echo $age_key === array_key_first($kids_age_guides) ? '' : ' hidden'; ?>>
          <fieldset class="size-range-fieldset">
            <legend>Size</legend>
            <div class="size-range-options kids-size-options">
              <?php foreach ($age_guide['sizes'] as $size_index => $size) { ?>
                <button
                  type="button"
                  class="size-range-option<?php echo $size_index === 0 ? ' is-selected' : ''; ?>"
                  data-kids-size="<?php echo htmlspecialchars($age_key . ':' . $size_index); ?>"
                  aria-pressed="<?php echo $size_index === 0 ? 'true' : 'false'; ?>">
                  <?php echo htmlspecialchars($size); ?>
                </button>
              <?php } ?>
            </div>
          </fieldset>
          <div class="measurement-table-wrap kids-basic-table-wrap">
            <table class="measurement-table kids-basic-table" data-kids-age-table="<?php echo htmlspecialchars($age_key); ?>">
              <caption class="visually-hidden">Kids' measurements for ages <?php echo htmlspecialchars($age_guide['label']); ?></caption>
              <thead></thead><tbody></tbody>
            </table>
          </div>
        </section>
      <?php } ?>
    </div>
  </section>
</main>

<script type="application/json" id="size-guide-data"><?php
echo json_encode($size_ranges, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="top-guide-data"><?php
echo json_encode($top_ranges, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="women-top-guide-data"><?php
echo json_encode($women_top_ranges, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="women-bottom-guide-data"><?php
echo json_encode($women_bottom_ranges, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="kids-top-guide-data"><?php
echo json_encode($kids_age_guides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script>
(function () {
  var guideData = JSON.parse(document.getElementById('size-guide-data').textContent);
  var topGuideData = JSON.parse(document.getElementById('top-guide-data').textContent);
  var womenTopGuideData = JSON.parse(document.getElementById('women-top-guide-data').textContent);
  var womenBottomGuideData = JSON.parse(document.getElementById('women-bottom-guide-data').textContent);
  var kidsAgeGuides = JSON.parse(document.getElementById('kids-top-guide-data').textContent);
  var rangeButtons = document.querySelectorAll('[data-size-range]');
  var tables = document.querySelectorAll('#bottom-guide .measurement-table');
  var selectedTopRanges = {
    regular: Object.keys(topGuideData.regular.groups)[0],
    long: Object.keys(topGuideData.long.groups)[0]
  };
  var selectedKidsSizes = {};
  Object.keys(kidsAgeGuides).forEach(function (ageKey) {
    selectedKidsSizes[ageKey] = 0;
  });
  function addCell(row, text, tagName, className) {
    var cell = document.createElement(tagName);
    cell.textContent = text;
    if (className) cell.className = className;
    row.appendChild(cell);
  }

  function renderTable(table, rangeData, measurement) {
    var head = table.querySelector('thead');
    var body = table.querySelector('tbody');
    var headerRow = document.createElement('tr');
    var title = measurement === 'inside_leg' ? 'Inside leg length' : 'Sizes in inches';

    head.replaceChildren();
    body.replaceChildren();
    addCell(headerRow, title, 'th', 'measurement-label');
    headerRow.firstChild.scope = 'col';
    rangeData.sizes.forEach(function (size) {
      var cell = document.createElement('th');
      cell.scope = 'col';
      cell.textContent = size + '"';
      headerRow.appendChild(cell);
    });
    head.appendChild(headerRow);

    if (measurement === 'inside_leg') {
      rangeData.inside_leg.forEach(function (measurementRow) {
        var row = document.createElement('tr');
        addCell(row, measurementRow.label, 'th', 'measurement-label');
        row.firstChild.scope = 'row';
        measurementRow.values.forEach(function (value) {
          addCell(row, value, 'td');
        });
        body.appendChild(row);
      });
      return;
    }

    var row = document.createElement('tr');
    var label = measurement === 'waist' ? 'Waist cm' : 'Low hip cm';
    addCell(row, label, 'th', 'measurement-label');
    row.firstChild.scope = 'row';
    rangeData[measurement].forEach(function (value) {
      addCell(row, value, 'td');
    });
    body.appendChild(row);
  }

  function selectRange(button) {
    var rangeData = guideData[button.getAttribute('data-size-range')];
    rangeButtons.forEach(function (rangeButton) {
      var selected = rangeButton === button;
      rangeButton.classList.toggle('is-selected', selected);
      rangeButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    tables.forEach(function (table) {
      renderTable(table, rangeData, table.getAttribute('data-measurement'));
    });
  }

  function renderTopTable(table, groupData, measurement) {
    var head = table.querySelector('thead');
    var body = table.querySelector('tbody');
    var headerRow = document.createElement('tr');
    var title = measurement.replace('_', ' ');
    var measurementRow = document.createElement('tr');

    head.replaceChildren();
    body.replaceChildren();
    addCell(headerRow, '', 'th', 'measurement-label');
    headerRow.firstChild.scope = 'col';
    groupData.sizes.forEach(function (size) {
      var cell = document.createElement('th');
      cell.scope = 'col';
      cell.textContent = size;
      headerRow.appendChild(cell);
    });
    head.appendChild(headerRow);

    groupData.size_reference.forEach(function (reference) {
      var row = document.createElement('tr');
      addCell(row, reference.label, 'th', 'measurement-label');
      row.firstChild.scope = 'row';
      reference.values.forEach(function (value) {
        addCell(row, value, 'td');
      });
      body.appendChild(row);
    });

    var label = measurement === 'arm_length' ? 'Arm length cm' :
      measurement === 'neckline' ? 'Neckline cm' :
      measurement.charAt(0).toUpperCase() + measurement.slice(1) + ' cm';
    addCell(measurementRow, label, 'th', 'measurement-label');
    measurementRow.firstChild.scope = 'row';
    groupData.measurements[measurement].forEach(function (value) {
      addCell(measurementRow, value, 'td');
    });
    body.appendChild(measurementRow);
  }

  function renderTopRange(fitKey, groupKey) {
    var groupData = topGuideData[fitKey].groups[groupKey];
    var panel = document.querySelector('[data-top-fit-panel="' + fitKey + '"]');
    panel.querySelectorAll('.top-measurement-table').forEach(function (table) {
      renderTopTable(table, groupData, table.getAttribute('data-top-measurement'));
    });
  }

  function renderWomenRange(groupKey) {
    var groupData = womenTopGuideData[groupKey];
    document.querySelectorAll('.women-measurement-table').forEach(function (table) {
      var head = table.querySelector('thead');
      var body = table.querySelector('tbody');
      var headerRow = document.createElement('tr');
      var measurement = table.getAttribute('data-women-measurement');
      var label = measurement === 'arm_length' ? 'Arm length cm' :
        measurement.replace('_', ' ').replace(/(^|\s)\S/g, function (letter) {
          return letter.toUpperCase();
        }) + ' cm';
      var measurementRow = document.createElement('tr');

      head.replaceChildren();
      body.replaceChildren();
      addCell(headerRow, '', 'th', 'measurement-label');
      headerRow.firstChild.scope = 'col';
      groupData.sizes.forEach(function (size) {
        var cell = document.createElement('th');
        cell.scope = 'col';
        cell.textContent = size;
        headerRow.appendChild(cell);
      });
      head.appendChild(headerRow);

      groupData.size_reference.forEach(function (reference) {
        var row = document.createElement('tr');
        addCell(row, reference.label, 'th', 'measurement-label');
        row.firstChild.scope = 'row';
        reference.values.forEach(function (value) {
          addCell(row, value, 'td');
        });
        body.appendChild(row);
      });

      addCell(measurementRow, label, 'th', 'measurement-label');
      measurementRow.firstChild.scope = 'row';
      groupData.measurements[measurement].forEach(function (value) {
        addCell(measurementRow, value, 'td');
      });
      body.appendChild(measurementRow);
    });
  }

  function renderWomenBottomRange(rangeKey) {
    var rangeData = womenBottomGuideData[rangeKey];
    document.querySelectorAll('.women-bottom-measurement-table').forEach(function (table) {
      var head = table.querySelector('thead');
      var body = table.querySelector('tbody');
      var headerRow = document.createElement('tr');
      var measurement = table.getAttribute('data-women-bottom-measurement');
      var label = measurement === 'inside_leg' ? 'Inside leg length cm' :
        measurement.replace('_', ' ').replace(/(^|\s)\S/g, function (letter) {
          return letter.toUpperCase();
        }) + ' cm';
      var measurementRow = document.createElement('tr');

      head.replaceChildren();
      body.replaceChildren();
      addCell(headerRow, '', 'th', 'measurement-label');
      headerRow.firstChild.scope = 'col';
      rangeData.sizes.forEach(function (size) {
        var cell = document.createElement('th');
        cell.scope = 'col';
        cell.textContent = size;
        headerRow.appendChild(cell);
      });
      head.appendChild(headerRow);

      rangeData.size_reference.forEach(function (reference) {
        var row = document.createElement('tr');
        addCell(row, reference.label, 'th', 'measurement-label');
        row.firstChild.scope = 'row';
        reference.values.forEach(function (value) {
          addCell(row, value, 'td');
        });
        body.appendChild(row);
      });

      addCell(measurementRow, label, 'th', 'measurement-label');
      measurementRow.firstChild.scope = 'row';
      rangeData.measurements[measurement].forEach(function (value) {
        addCell(measurementRow, value, 'td');
      });
      body.appendChild(measurementRow);
    });
  }

  function renderKidsTable(table, ageGuide, sizeIndex) {
    var head = table.querySelector('thead');
    var body = table.querySelector('tbody');
    var headerRow = document.createElement('tr');

    head.replaceChildren();
    body.replaceChildren();
    addCell(headerRow, 'Measurement', 'th', 'measurement-label');
    headerRow.firstChild.scope = 'col';
    addCell(headerRow, ageGuide.sizes[sizeIndex], 'th');
    headerRow.lastChild.scope = 'col';
    head.appendChild(headerRow);

    ageGuide.measurements.forEach(function (dataRow) {
      var row = document.createElement('tr');
      addCell(row, dataRow.label, 'th', 'measurement-label');
      row.firstChild.scope = 'row';
      addCell(row, dataRow.values[sizeIndex], 'td');
      body.appendChild(row);
    });
  }

  function renderKidsAgeGuide(ageKey) {
    renderKidsTable(
      document.querySelector('[data-kids-age-table="' + ageKey + '"]'),
      kidsAgeGuides[ageKey],
      selectedKidsSizes[ageKey]
    );
  }

  rangeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      selectRange(button);
    });
  });

  document.querySelectorAll('[data-top-fit]').forEach(function (button) {
    button.addEventListener('click', function () {
      var fitKey = button.getAttribute('data-top-fit');
      document.querySelectorAll('[data-top-fit]').forEach(function (fitButton) {
        var selected = fitButton === button;
        fitButton.classList.toggle('is-selected', selected);
        fitButton.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      document.querySelectorAll('[data-top-fit-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-top-fit-panel') !== fitKey;
      });
      renderTopRange(fitKey, selectedTopRanges[fitKey]);
    });
  });

  document.querySelectorAll('[data-top-range]').forEach(function (button) {
    button.addEventListener('click', function () {
      var selection = button.getAttribute('data-top-range').split(':');
      var fitKey = selection[0];
      var groupKey = selection[1];
      selectedTopRanges[fitKey] = groupKey;
      document.querySelectorAll('[data-top-fit-panel="' + fitKey + '"] [data-top-range]').forEach(function (rangeButton) {
        var selected = rangeButton === button;
        rangeButton.classList.toggle('is-selected', selected);
        rangeButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      renderTopRange(fitKey, groupKey);
    });
  });

  document.querySelectorAll('[data-guide-type]').forEach(function (button) {
    button.addEventListener('click', function () {
      var type = button.getAttribute('data-guide-type');
      document.querySelectorAll('[data-guide-type]').forEach(function (tab) {
        var selected = tab === button;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      document.querySelectorAll('[data-guide-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-guide-panel') !== type;
      });
    });
  });

  document.querySelectorAll('[data-department-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      var department = button.getAttribute('data-department-tab');
      var isWomen = department === 'women';
      var isKids = department === 'kids';
      document.querySelectorAll('[data-department-tab]').forEach(function (tab) {
        var selected = tab === button;
        tab.classList.toggle('is-selected', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      document.querySelectorAll('[data-department-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-department-panel') !== department;
      });
      document.getElementById('guide-department-title').textContent = isWomen ? 'Women' : 'Men';
      if (isKids) {
        document.getElementById('guide-department-title').textContent = 'Kids';
      }
      document.title = (isWomen ? "Women's" : isKids ? "Kids'" : "Men's") + ' Size Guide | Soigné';
    });
  });

  document.querySelectorAll('[data-women-range]').forEach(function (button) {
    button.addEventListener('click', function () {
      var rangeKey = button.getAttribute('data-women-range');
      document.querySelectorAll('[data-women-range]').forEach(function (rangeButton) {
        var selected = rangeButton === button;
        rangeButton.classList.toggle('is-selected', selected);
        rangeButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      renderWomenRange(rangeKey);
    });
  });

  document.querySelectorAll('[data-women-guide-type]').forEach(function (button) {
    button.addEventListener('click', function () {
      var type = button.getAttribute('data-women-guide-type');
      document.querySelectorAll('[data-women-guide-type]').forEach(function (tab) {
        var selected = tab === button;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      document.querySelectorAll('[data-women-guide-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-women-guide-panel') !== type;
      });
    });
  });

  document.querySelectorAll('[data-women-bottom-range]').forEach(function (button) {
    button.addEventListener('click', function () {
      var rangeKey = button.getAttribute('data-women-bottom-range');
      document.querySelectorAll('[data-women-bottom-range]').forEach(function (rangeButton) {
        var selected = rangeButton === button;
        rangeButton.classList.toggle('is-selected', selected);
        rangeButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      renderWomenBottomRange(rangeKey);
    });
  });

  document.querySelectorAll('[data-kids-age]').forEach(function (button) {
    button.addEventListener('click', function () {
      var ageKey = button.getAttribute('data-kids-age');
      document.querySelectorAll('[data-kids-age]').forEach(function (ageButton) {
        var selected = ageButton === button;
        ageButton.classList.toggle('is-selected', selected);
        ageButton.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      document.querySelectorAll('[data-kids-age-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-kids-age-panel') !== ageKey;
      });
      renderKidsAgeGuide(ageKey);
    });
  });

  document.querySelectorAll('[data-kids-size]').forEach(function (button) {
    button.addEventListener('click', function () {
      var selection = button.getAttribute('data-kids-size').split(':');
      var ageKey = selection[0];
      var sizeIndex = Number(selection[1]);
      selectedKidsSizes[ageKey] = sizeIndex;
      document.querySelectorAll('[data-kids-age-panel="' + ageKey + '"] [data-kids-size]').forEach(function (sizeButton) {
        var selected = sizeButton === button;
        sizeButton.classList.toggle('is-selected', selected);
        sizeButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      renderKidsAgeGuide(ageKey);
    });
  });

  document.querySelectorAll('.measurement-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      var panel = document.getElementById(toggle.getAttribute('aria-controls'));
      toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      panel.hidden = expanded;
    });
  });

  if (rangeButtons.length) selectRange(rangeButtons[0]);
  renderTopRange('regular', selectedTopRanges.regular);
  renderTopRange('long', selectedTopRanges.long);
  renderWomenRange(Object.keys(womenTopGuideData)[0]);
  renderWomenBottomRange(document.querySelector('[data-women-bottom-range]').getAttribute('data-women-bottom-range'));
  Object.keys(kidsAgeGuides).forEach(function (ageKey) {
    renderKidsAgeGuide(ageKey);
  });
})();
</script>

<?php include_once('includes/footer.php'); ?>
