<?php
session_start();

$page_title = "Size Guide | Soigné";
$extra_css = 'size-guide.css';
$dept = $_GET['dept'] ?? 'men';
if (!is_string($dept) || !in_array($dept, ['men', 'women', 'kids'], true)) {
    $dept = 'men';
}
$type = $_GET['type'] ?? 'top';
if (!is_string($type) || !in_array($type, ['top', 'bottom'], true)) {
    $type = 'top';
}

include_once('includes/header.php');
?>
<main class="size-guide-page">
  <header class="size-guide-heading">
    <h1>Size Guide</h1>
  </header>

  <input class="visually-hidden department-guide-radio" type="radio" name="size-guide-department" id="department-men" <?php echo $dept === 'men' ? 'checked' : ''; ?>>
  <input class="visually-hidden department-guide-radio" type="radio" name="size-guide-department" id="department-women" <?php echo $dept === 'women' ? 'checked' : ''; ?>>
  <input class="visually-hidden department-guide-radio" type="radio" name="size-guide-department" id="department-kids" <?php echo $dept === 'kids' ? 'checked' : ''; ?>>

  <nav class="department-guide-tabs" aria-label="Size guide departments">
    <label class="department-guide-tab" for="department-men">Men</label>
    <label class="department-guide-tab" for="department-women">Women</label>
    <label class="department-guide-tab" for="department-kids">Kids</label>
  </nav>

  <section class="size-guide-card" id="men-size-guide" aria-label="Men's size guide">
    <input class="visually-hidden guide-type-radio" type="radio" name="men-guide-type" id="men-type-top" <?php echo $type === 'top' ? 'checked' : ''; ?>>
    <input class="visually-hidden guide-type-radio" type="radio" name="men-guide-type" id="men-type-bottom" <?php echo $type === 'bottom' ? 'checked' : ''; ?>>
    <nav class="guide-type-tabs" aria-label="Men's size guide type">
      <label class="guide-type-tab" for="men-type-top">Tops</label>
      <label class="guide-type-tab" for="men-type-bottom">Bottoms</label>
    </nav>

    <div class="top-guide bottom-guide" id="top-guide">
      <h2>Men's tops</h2>

      <input class="visually-hidden top-fit-radio" type="radio" name="men-top-fit" id="men-fit-regular" checked>
      <input class="visually-hidden top-fit-radio" type="radio" name="men-top-fit" id="men-fit-long">
      <nav class="top-fit-tabs" aria-label="Men's top fit">
        <label class="top-fit-tab" for="men-fit-regular">Regular</label>
        <label class="top-fit-tab" for="men-fit-long">Long</label>
      </nav>

              <section class="top-fit-panel" id="men-fit-panel-regular">
          <div class="measurement-accordions">
                                                    <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-regular-measurement" id="control-top-regular-chest-panel" checked>
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-regular-chest-panel">
                  Chest<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-regular-chest-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>74-78</td><td>78-86</td><td>86-90</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>90-94</td><td>94-98</td><td>98-102</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · XXL-3XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>102-106</td><td>110-114</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-regular-measurement" id="control-top-regular-waist-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-regular-waist-panel">
                  Waist<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-regular-waist-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>62-66</td><td>66-74</td><td>74-78</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>78-82</td><td>82-86</td><td>86-90</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · XXL-3XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>90-94</td><td>98-102</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-regular-measurement" id="control-top-regular-arm-length-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-regular-arm-length-panel">
                  Arm length<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-regular-arm-length-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>59</td><td>59</td><td>59-60</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>60-61</td><td>61-62</td><td>62-63</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · XXL-3XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>63-64</td><td>65-66</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-regular-measurement" id="control-top-regular-neckline-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-regular-neckline-panel">
                  Neckline<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-regular-neckline-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>33</td><td>34-35</td><td>36</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>38</td><td>39-40</td><td>41</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · XXL-3XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>42</td><td>45</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                      </div>
        </section>
              <section class="top-fit-panel" id="men-fit-panel-long">
          <div class="measurement-accordions">
                                                    <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-long-measurement" id="control-top-long-chest-panel" checked>
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-long-chest-panel">
                  Chest<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-long-chest-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · XS/L-M/L</caption><thead><tr><th scope="col">Size</th><th scope="col">XS/L</th><th scope="col">S/L</th><th scope="col">M/L</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>78-86</td><td>86-94</td><td>94-102</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · L/L-XXL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">L/L</th><th scope="col">XL/L</th><th scope="col">XXL/L</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>98-102</td><td>102-110</td><td>110-118</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Chest · 3XL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">3XL/L</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>118-126</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-long-measurement" id="control-top-long-waist-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-long-waist-panel">
                  Waist<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-long-waist-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · XS/L-M/L</caption><thead><tr><th scope="col">Size</th><th scope="col">XS/L</th><th scope="col">S/L</th><th scope="col">M/L</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>66-74</td><td>74-82</td><td>82-90</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · L/L-XXL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">L/L</th><th scope="col">XL/L</th><th scope="col">XXL/L</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>86-90</td><td>90-98</td><td>98-106</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Waist · 3XL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">3XL/L</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>106-114</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-long-measurement" id="control-top-long-arm-length-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-long-arm-length-panel">
                  Arm length<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-long-arm-length-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · XS/L-M/L</caption><thead><tr><th scope="col">Size</th><th scope="col">XS/L</th><th scope="col">S/L</th><th scope="col">M/L</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>62</td><td>62-63</td><td>63-64</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · L/L-XXL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">L/L</th><th scope="col">XL/L</th><th scope="col">XXL/L</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>64-65</td><td>65-66</td><td>66-67</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Arm length · 3XL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">3XL/L</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>67-68</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                                        <section class="measurement-section">
                <input class="visually-hidden measurement-radio" type="radio" name="men-top-long-measurement" id="control-top-long-neckline-panel" >
                <h4 class="measurement-heading"><label class="measurement-toggle" for="control-top-long-neckline-panel">
                  Neckline<span class="toggle-mark"></span>
                </label></h4>
                <div class="measurement-panel" id="top-long-neckline-panel">
                  <div class="measurement-table-wrap">
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · XS/L-M/L</caption><thead><tr><th scope="col">Size</th><th scope="col">XS/L</th><th scope="col">S/L</th><th scope="col">M/L</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>34-35</td><td>36-37</td><td>38-39</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · L/L-XXL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">L/L</th><th scope="col">XL/L</th><th scope="col">XXL/L</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>40-41</td><td>42-43</td><td>44-45</td></tr></tbody></table>
                                          <table class="measurement-table"><caption class="visually-hidden">Neckline · 3XL/L</caption><thead><tr><th scope="col">Size</th><th scope="col">3XL/L</th></tr></thead><tbody><tr><th scope="row">Neckline (cm)</th><td>46-47</td></tr></tbody></table>
                                      </div>
                </div>
              </section>
                      </div>
        </section>
          </div>

    <div class="bottom-guide" id="bottom-guide">
      <h2>Men's bottoms</h2>

      <div class="measurement-accordions">
        <section class="measurement-section">
          <input class="visually-hidden measurement-radio" type="radio" name="men-bottom-measurement" id="control-waist-panel" checked>
          <h3 class="measurement-heading"><label class="measurement-toggle" for="control-waist-panel">Waist<span class="toggle-mark"></span></label></h3>
          <div class="measurement-panel" id="waist-panel">
            <div class="measurement-table-wrap">
                            <table class="measurement-table"><caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">XS-M · Size</th><th scope="col">XS</th><th scope="col">S</th><th scope="col">M</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>64-67</td><td>67-70</td><td>70-73</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">S-L · Size</th><th scope="col">S</th><th scope="col">M</th><th scope="col">L</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>73-76</td><td>76-79</td><td>79-82</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">M-XL · Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>82-85</td><td>85-88</td><td>88-91</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">L-XXL · Size</th><th scope="col">L</th><th scope="col">XL</th><th scope="col">XXL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>91-94</td><td>97-100</td><td>103-106</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Waist measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">3XL-5XL · Size</th><th scope="col">3XL</th><th scope="col">4XL</th><th scope="col">5XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>109-112</td><td>115-118</td><td>121-124</td></tr></tbody></table>
                          </div>
          </div>
        </section>

        <section class="measurement-section">
          <input class="visually-hidden measurement-radio" type="radio" name="men-bottom-measurement" id="control-low-hip-panel">
          <h3 class="measurement-heading"><label class="measurement-toggle" for="control-low-hip-panel">Low hip<span class="toggle-mark"></span></label></h3>
          <div class="measurement-panel" id="low-hip-panel">
            <div class="measurement-table-wrap">
                            <table class="measurement-table"><caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">XS-M · Size</th><th scope="col">XS</th><th scope="col">S</th><th scope="col">M</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>84-86</td><td>86-88</td><td>88-90</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">S-L · Size</th><th scope="col">S</th><th scope="col">M</th><th scope="col">L</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>90-92</td><td>92-94</td><td>94-96</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">M-XL · Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>96-98</td><td>98-100</td><td>100-102</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">L-XXL · Size</th><th scope="col">L</th><th scope="col">XL</th><th scope="col">XXL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>102-104</td><td>106-108</td><td>110-112</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Low hip measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">3XL-5XL · Size</th><th scope="col">3XL</th><th scope="col">4XL</th><th scope="col">5XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>114-116</td><td>118-120</td><td>122-124</td></tr></tbody></table>
                          </div>
          </div>
        </section>

        <section class="measurement-section">
          <input class="visually-hidden measurement-radio" type="radio" name="men-bottom-measurement" id="control-inside-leg-panel">
          <h3 class="measurement-heading"><label class="measurement-toggle" for="control-inside-leg-panel">Inside leg length<span class="toggle-mark"></span></label></h3>
          <div class="measurement-panel" id="inside-leg-panel">
            <div class="measurement-table-wrap">
                            <table class="measurement-table"><caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">XS-M · Size</th><th scope="col">XS</th><th scope="col">S</th><th scope="col">M</th></tr></thead><tbody><tr><th scope="row">30&quot; length cm</th><td>75-77</td><td>75-77</td><td>76-78</td></tr><tr><th scope="row">32&quot; length cm</th><td>79-81</td><td>79-81</td><td>80-82</td></tr><tr><th scope="row">34&quot; length cm</th><td>83-85</td><td>83-85</td><td>84-86</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">S-L · Size</th><th scope="col">S</th><th scope="col">M</th><th scope="col">L</th></tr></thead><tbody><tr><th scope="row">30&quot; length cm</th><td>76-78</td><td>77-79</td><td>77-79</td></tr><tr><th scope="row">32&quot; length cm</th><td>80-82</td><td>81-83</td><td>81-83</td></tr><tr><th scope="row">34&quot; length cm</th><td>84-86</td><td>85-87</td><td>85-87</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">M-XL · Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">30&quot; length cm</th><td>78-80</td><td>78-80</td><td>79-81</td></tr><tr><th scope="row">32&quot; length cm</th><td>82-84</td><td>82-84</td><td>83-85</td></tr><tr><th scope="row">34&quot; length cm</th><td>86-88</td><td>86-88</td><td>87-89</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">L-XXL · Size</th><th scope="col">L</th><th scope="col">XL</th><th scope="col">XXL</th></tr></thead><tbody><tr><th scope="row">30&quot; length cm</th><td>79-81</td><td>80-82</td><td>81-83</td></tr><tr><th scope="row">32&quot; length cm</th><td>83-85</td><td>84-86</td><td>85-87</td></tr><tr><th scope="row">34&quot; length cm</th><td>87-89</td><td>88-90</td><td>89-91</td></tr></tbody></table>
                            <table class="measurement-table"><caption class="visually-hidden">Inside leg length measurements in centimeters by alpha clothing size</caption><thead><tr><th scope="col">3XL-5XL · Size</th><th scope="col">3XL</th><th scope="col">4XL</th><th scope="col">5XL</th></tr></thead><tbody><tr><th scope="row">30&quot; length cm</th><td>82-84</td><td>83-85</td><td>84-86</td></tr><tr><th scope="row">32&quot; length cm</th><td>86-88</td><td>87-89</td><td>88-90</td></tr><tr><th scope="row">34&quot; length cm</th><td>90-92</td><td>91-93</td><td>92-94</td></tr></tbody></table>
                          </div>
          </div>
        </section>
      </div>
    </div>
  </section>

  <section class="size-guide-card women-size-guide" id="women-size-guide" aria-label="Women's size guide">
    <input class="visually-hidden guide-type-radio" type="radio" name="women-guide-type" id="women-type-top" <?php echo $type === 'top' ? 'checked' : ''; ?>>
    <input class="visually-hidden guide-type-radio" type="radio" name="women-guide-type" id="women-type-bottom" <?php echo $type === 'bottom' ? 'checked' : ''; ?>>
    <nav class="guide-type-tabs" aria-label="Women's size guide type">
      <label class="guide-type-tab" for="women-type-top">Tops</label>
      <label class="guide-type-tab" for="women-type-bottom">Bottoms</label>
    </nav>

    <div class="bottom-guide" id="women-top-guide">
      <h2>Women's tops</h2>
      <p class="women-fit-label">Regular</p>

      <div class="measurement-accordions">
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-top-measurement" id="control-women-chest-panel" checked>
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-chest-panel">
              Chest<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-chest-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Chest · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>74-78</td><td>78-82</td><td>82-90</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Chest · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>90-94</td><td>94-102</td><td>102-110</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Chest · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Chest (cm)</th><td>110-118</td><td>118-126</td><td>126-134</td></tr></tbody></table>
                              </div>
            </div>
          </section>
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-top-measurement" id="control-women-waist-panel" >
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-waist-panel">
              Waist<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-waist-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>58-62</td><td>62-66</td><td>66-74</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>74-78</td><td>78-86</td><td>86-94</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>94-102</td><td>102-110</td><td>110-118</td></tr></tbody></table>
                              </div>
            </div>
          </section>
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-top-measurement" id="control-women-arm-length-panel" >
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-arm-length-panel">
              Arm length<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-arm-length-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Arm length · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>56</td><td>56</td><td>57</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Arm length · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>58</td><td>59</td><td>60</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Arm length · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Arm length (cm)</th><td>61</td><td>62</td><td>63</td></tr></tbody></table>
                              </div>
            </div>
          </section>
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-top-measurement" id="control-women-low-hip-panel" >
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-low-hip-panel">
              Low hip<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-low-hip-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>79-83</td><td>83-87</td><td>87-94.5</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>94.5-98.5</td><td>98.5-106.5</td><td>106.5-114.5</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>114.5-122.5</td><td>122.5-130.5</td><td>130.5-138.5</td></tr></tbody></table>
                              </div>
            </div>
          </section>
              </div>
    </div>

    <div class="bottom-guide" id="women-bottom-guide">
      <h2>Women's bottoms</h2>
      <p class="women-fit-label">Regular</p>

      <div class="measurement-accordions">
                                    <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-bottom-measurement" id="control-women-bottom-waist-panel" checked>
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-bottom-waist-panel">
              Waist<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-bottom-waist-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>58-62</td><td>62-66</td><td>66-70</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>70-74</td><td>74-78</td><td>78-82</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>82-86</td><td>86-90</td><td>90-94</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · 5XL-7XL</caption><thead><tr><th scope="col">Size</th><th scope="col">5XL</th><th scope="col">6XL</th><th scope="col">7XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>94-100</td><td>100-106</td><td>106-112</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · 8XL-10XL</caption><thead><tr><th scope="col">Size</th><th scope="col">8XL</th><th scope="col">9XL</th><th scope="col">10XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>112-118</td><td>118-124</td><td>124-130</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Waist · 11XL</caption><thead><tr><th scope="col">Size</th><th scope="col">11XL</th></tr></thead><tbody><tr><th scope="row">Waist (cm)</th><td>130-136</td></tr></tbody></table>
                              </div>
            </div>
          </section>
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-bottom-measurement" id="control-women-bottom-low-hip-panel" >
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-bottom-low-hip-panel">
              Low hip<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-bottom-low-hip-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>79-83</td><td>83-87</td><td>87-91</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>91-95</td><td>95-99</td><td>99-103</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>103-107</td><td>107-111</td><td>111-115</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · 5XL-7XL</caption><thead><tr><th scope="col">Size</th><th scope="col">5XL</th><th scope="col">6XL</th><th scope="col">7XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>115-121</td><td>121-127</td><td>127-133</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · 8XL-10XL</caption><thead><tr><th scope="col">Size</th><th scope="col">8XL</th><th scope="col">9XL</th><th scope="col">10XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>133-139</td><td>139-145</td><td>145-151</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Low hip · 11XL</caption><thead><tr><th scope="col">Size</th><th scope="col">11XL</th></tr></thead><tbody><tr><th scope="row">Low hip (cm)</th><td>151-157</td></tr></tbody></table>
                              </div>
            </div>
          </section>
                            <section class="measurement-section">
            <input class="visually-hidden measurement-radio" type="radio" name="women-bottom-measurement" id="control-women-bottom-inside-leg-panel" >
            <h3 class="measurement-heading"><label class="measurement-toggle" for="control-women-bottom-inside-leg-panel">
              Inside leg length<span class="toggle-mark"></span>
            </label></h3>
            <div class="measurement-panel" id="women-bottom-inside-leg-panel">
              <div class="measurement-table-wrap">
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · XXS-S</caption><thead><tr><th scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>74.5</td><td>74.5</td><td>74</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · M-XL</caption><thead><tr><th scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>73.5</td><td>73</td><td>72.5</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · XXL-4XL</caption><thead><tr><th scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th><th scope="col">4XL</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>72</td><td>71.5</td><td>71</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · 5XL-7XL</caption><thead><tr><th scope="col">Size</th><th scope="col">5XL</th><th scope="col">6XL</th><th scope="col">7XL</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>70.5</td><td>70</td><td>69.5</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · 8XL-10XL</caption><thead><tr><th scope="col">Size</th><th scope="col">8XL</th><th scope="col">9XL</th><th scope="col">10XL</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>69</td><td>68.5</td><td>68</td></tr></tbody></table>
                                  <table class="measurement-table"><caption class="visually-hidden">Inside leg length · 11XL</caption><thead><tr><th scope="col">Size</th><th scope="col">11XL</th></tr></thead><tbody><tr><th scope="row">Inside leg length (cm)</th><td>67.5</td></tr></tbody></table>
                              </div>
            </div>
          </section>
              </div>
    </div>
  </section>

  <section class="size-guide-card kids-size-guide" id="kids-size-guide" aria-label="Kids' size guide">
    <input class="visually-hidden guide-type-radio" type="radio" name="kids-guide-type" id="kids-type-top" <?php echo $type === 'top' ? 'checked' : ''; ?>>
    <input class="visually-hidden guide-type-radio" type="radio" name="kids-guide-type" id="kids-type-bottom" <?php echo $type === 'bottom' ? 'checked' : ''; ?>>
    <nav class="guide-type-tabs" aria-label="Kids' size guide type">
      <label class="guide-type-tab" for="kids-type-top">Tops</label>
      <label class="guide-type-tab" for="kids-type-bottom">Bottoms</label>
    </nav>

          <div class="bottom-guide" id="kids-top-guide">
        <h2>Kids' tops</h2>
        <div class="measurement-accordions">
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-top-measurement" id="control-kids-top-measurement-0" checked>
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-top-measurement-0">
                Numeric size<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>6-7</td><td>8-9</td><td>8-9 Plus</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>10-12</td><td>14-16</td><td>10-12 Plus</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>18-20</td><td>14-16 Plus</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-top-measurement" id="control-kids-top-measurement-1" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-top-measurement-1">
                Age<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>7-8</td><td>8-10</td><td>8-10</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>10-12</td><td>12-13</td><td>10-12</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>13-15</td><td>12-13</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-top-measurement" id="control-kids-top-measurement-2" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-top-measurement-2">
                Height (cm)<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>122-128</td><td>128-137</td><td>131-140</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>137-147</td><td>147-158</td><td>140-150</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>158-170</td><td>150-160</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-top-measurement" id="control-kids-top-measurement-3" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-top-measurement-3">
                Chest (cm)<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Chest (cm) measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Chest (cm)</th><td>61.5-65</td><td>65-70</td><td>70-78</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Chest (cm) measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Chest (cm)</th><td>70-77</td><td>77-84</td><td>78-86</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Chest (cm) measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Chest (cm)</th><td>84-91.5</td><td>86-94</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                  </div>
      </div>
          <div class="bottom-guide" id="kids-bottom-guide">
        <h2>Kids' bottoms</h2>
        <div class="measurement-accordions">
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-bottom-measurement" id="control-kids-bottom-measurement-0" checked>
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-bottom-measurement-0">
                Numeric size<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>6-7</td><td>8-9</td><td>8-9 Plus</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>10-12</td><td>14-16</td><td>10-12 Plus</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Numeric size measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Numeric size</th><td>18-20</td><td>14-16 Plus</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-bottom-measurement" id="control-kids-bottom-measurement-1" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-bottom-measurement-1">
                Age<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>7-8</td><td>8-10</td><td>8-10</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>10-12</td><td>12-13</td><td>10-12</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Age measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Age</th><td>13-15</td><td>12-13</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-bottom-measurement" id="control-kids-bottom-measurement-2" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-bottom-measurement-2">
                Height (cm)<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>122-128</td><td>128-137</td><td>131-140</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>137-147</td><td>147-158</td><td>140-150</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Height (cm) measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Height (cm)</th><td>158-170</td><td>150-160</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-bottom-measurement" id="control-kids-bottom-measurement-3" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-bottom-measurement-3">
                Waist (cm)<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Waist (cm) measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Waist (cm)</th><td>50-56</td><td>56-62</td><td>61-67</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Waist (cm) measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Waist (cm)</th><td>62-68</td><td>68-74</td><td>67-73</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Waist (cm) measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Waist (cm)</th><td>74-80</td><td>73-79</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                                  <section class="measurement-section">
              <input class="visually-hidden measurement-radio" type="radio" name="kids-bottom-measurement" id="control-kids-bottom-measurement-4" >
              <h3 class="measurement-heading"><label class="measurement-toggle" for="control-kids-bottom-measurement-4">
                Hip (cm)<span class="toggle-mark"></span>
              </label></h3>
              <div class="measurement-panel">
                <div class="measurement-table-wrap kids-basic-table-wrap">
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Hip (cm) measurements · XXS-S</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXS</th><th scope="col">XS</th><th scope="col">S</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Hip (cm)</th><td>63-67</td><td>67-74</td><td>78-82</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Hip (cm) measurements · M-XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">M</th><th scope="col">L</th><th scope="col">XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Hip (cm)</th><td>74-80</td><td>80-88</td><td>82-88</td></tr></tbody></table>
                                      <table class="measurement-table kids-basic-table"><caption class="visually-hidden">Kids' Hip (cm) measurements · XXL-3XL</caption><thead><tr><th class="measurement-label" scope="col">Size</th><th scope="col">XXL</th><th scope="col">3XL</th></tr></thead><tbody><tr><th class="measurement-label" scope="row">Hip (cm)</th><td>88-96</td><td>88-95</td></tr></tbody></table>
                                  </div>
              </div>
            </section>
                  </div>
      </div>
      </section>
</main>

<?php include_once('includes/footer.php'); ?>
