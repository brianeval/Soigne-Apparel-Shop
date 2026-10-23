(() => {
  const inventoryDepartmentFilter = document.querySelector('[data-inventory-department]');
  const inventoryColorFilter = document.querySelector('[data-inventory-color]');
  const inventorySizeFilter = document.querySelector('[data-inventory-size]');

  if (inventoryDepartmentFilter) {
    function updateDepartmentOptions(filter, datasetName, allLabel) {
      if (!filter) return;
      const optionsByDepartment = JSON.parse(filter.dataset[datasetName]);
      const department = inventoryDepartmentFilter.value;
      const options = department ? (optionsByDepartment[department] || []) : Object.values(optionsByDepartment).flat();
      const uniqueOptions = [...new Set(options)];
      const currentValue = filter.value;

      filter.replaceChildren(new Option(allLabel, ''));
      uniqueOptions.forEach((value) => filter.add(new Option(value, value)));
      filter.value = uniqueOptions.includes(currentValue) ? currentValue : '';
    }

    function updateDepartmentFilters() {
      updateDepartmentOptions(inventoryColorFilter, 'colorOptions', 'All colors');
      updateDepartmentOptions(inventorySizeFilter, 'sizeOptions', 'All sizes');
    }

    inventoryDepartmentFilter.addEventListener('change', updateDepartmentFilters);
    updateDepartmentFilters();
  }

  const form = document.querySelector('[data-restock-form]');
  if (!form) return;

  const supplierSelect = form.querySelector('[data-restock-supplier]');
  const linesContainer = form.querySelector('[data-restock-lines]');
  const optionTemplate = document.getElementById('restock-variant-options');
  const lineTemplate = document.getElementById('restock-line-template');
  const totalDisplay = form.querySelector('[data-restock-total]');
  const reviewError = form.querySelector('[data-review-error]');
  const reviewStep = form.querySelector('[data-restock-review]');
  const editStep = form.querySelector('[data-restock-edit]');
  const confirmedInput = form.querySelector('[data-restock-confirmed]');
  const formatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
  const maximumTotalCents = 9007199254740991;

  function moneyFromCents(cents) {
    return formatter.format(cents / 100);
  }

  function costInCents(value) {
    if (!/^\d{1,8}(?:\.\d{1,2})?$/.test(value)) return null;
    const parts = value.split('.');
    return Number(parts[0]) * 100 + Number((parts[1] || '').padEnd(2, '0'));
  }

  function currentSelections(exceptSelect) {
    return new Set(Array.from(linesContainer.querySelectorAll('[data-restock-variant]'))
      .filter((select) => select !== exceptSelect && select.value)
      .map((select) => select.value));
  }

  function refreshVariantSelect(select) {
    const selectedValue = select.value;
    const selectedByOtherLine = currentSelections(select);
    const supplierId = supplierSelect.value;
    const fragment = optionTemplate.content.cloneNode(true);

    Array.from(fragment.querySelectorAll('option')).forEach((option) => {
      if (!option.value) return;
      if (!supplierId || option.dataset.supplierId !== supplierId) {
        option.remove();
        return;
      }
      option.disabled = selectedByOtherLine.has(option.value);
    });

    select.replaceChildren(fragment);
    if (Array.from(select.options).some((option) => option.value === selectedValue && !option.disabled)) {
      select.value = selectedValue;
    }
  }

  function updateStockLabel(row) {
    const select = row.querySelector('[data-restock-variant]');
    const option = select.selectedOptions[0];
    const label = row.querySelector('[data-current-stock]');
    label.textContent = option && option.value
      ? `Current stock: ${Number(option.dataset.stock).toLocaleString()}`
      : 'Current stock: —';
  }

  function updateLineTotal(row) {
    const quantity = Number(row.querySelector('[data-restock-quantity]').value);
    const cost = costInCents(row.querySelector('[data-restock-cost]').value);
    const lineTotal = Number.isSafeInteger(quantity) && quantity > 0 && cost !== null
      ? quantity * cost
      : null;
    row.dataset.lineTotalCents = Number.isSafeInteger(lineTotal) ? String(lineTotal) : 'overflow';
    row.querySelector('[data-line-total]').textContent = Number.isSafeInteger(lineTotal)
      ? moneyFromCents(lineTotal)
      : lineTotal === null ? moneyFromCents(0) : 'Over limit';
  }

  function updateTotalsAndControls() {
    const rows = Array.from(linesContainer.querySelectorAll('[data-restock-line]'));
    let total = 0;
    let totalOverLimit = false;
    rows.forEach((row) => {
      updateStockLabel(row);
      updateLineTotal(row);
      const lineTotal = Number(row.dataset.lineTotalCents);
      if (!Number.isSafeInteger(lineTotal) || total > maximumTotalCents - lineTotal) {
        totalOverLimit = true;
      } else {
        total += lineTotal;
      }
    });
    totalDisplay.textContent = totalOverLimit ? 'Over limit' : moneyFromCents(total);
    rows.forEach((row) => {
      row.querySelector('[data-remove-restock-line]').disabled = rows.length === 1;
    });
    form.querySelector('[data-add-restock-line]').disabled = rows.length >= 50;
  }

  function refreshAllRows() {
    linesContainer.querySelectorAll('[data-restock-variant]').forEach(refreshVariantSelect);
    updateTotalsAndControls();
  }

  function showEditStep() {
    editStep.hidden = false;
    reviewStep.hidden = true;
    confirmedInput.value = '0';
    reviewError.hidden = true;
    reviewError.textContent = '';
  }

  function reviewRestock() {
    reviewError.hidden = true;
    reviewError.textContent = '';
    refreshAllRows();

    if (!supplierSelect.value || !form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const rows = Array.from(linesContainer.querySelectorAll('[data-restock-line]'));
    const variantIds = rows.map((row) => row.querySelector('[data-restock-variant]').value);
    if (new Set(variantIds).size !== variantIds.length) {
      reviewError.textContent = 'Each variant can only be listed once. Combine quantities for duplicate variants.';
      reviewError.hidden = false;
      return;
    }

    const reviewLines = form.querySelector('[data-review-lines]');
    reviewLines.replaceChildren();
    let totalCents = 0;

    for (const row of rows) {
      const variantSelect = row.querySelector('[data-restock-variant]');
      const selectedOption = variantSelect.selectedOptions[0];
      const quantityValue = row.querySelector('[data-restock-quantity]').value;
      const quantity = Number(quantityValue);
      const costCents = costInCents(row.querySelector('[data-restock-cost]').value);
      const currentStock = Number(selectedOption.dataset.stock);
      if (!/^\d+$/.test(quantityValue)) {
        reviewError.textContent = 'Enter a positive whole-number quantity for every variant.';
        reviewError.hidden = false;
        return;
      }
      if (costCents === null || costCents < 1) {
        reviewError.textContent = 'Enter a valid unit purchase cost for every variant.';
        reviewError.hidden = false;
        return;
      }
      const lineTotal = quantity * costCents;

      if (currentStock + quantity > 2147483647 || !Number.isSafeInteger(lineTotal)
          || lineTotal > maximumTotalCents || totalCents > maximumTotalCents - lineTotal) {
        reviewError.textContent = 'The requested stock or restock total exceeds the allowed maximum.';
        reviewError.hidden = false;
        return;
      }
      totalCents += lineTotal;

      const reviewRow = document.createElement('tr');
      const values = [
        selectedOption.textContent,
        currentStock.toLocaleString(),
        quantity.toLocaleString(),
        moneyFromCents(costCents),
        moneyFromCents(lineTotal),
      ];
      values.forEach((value) => {
        const cell = document.createElement('td');
        cell.textContent = value;
        reviewRow.appendChild(cell);
      });
      reviewLines.appendChild(reviewRow);
    }

    form.querySelector('[data-review-supplier]').textContent =
      `Supplier: ${supplierSelect.selectedOptions[0].textContent}`;
    form.querySelector('[data-review-total]').textContent = moneyFromCents(totalCents);
    confirmedInput.value = '1';
    editStep.hidden = true;
    reviewStep.hidden = false;
  }

  supplierSelect.addEventListener('change', refreshAllRows);
  linesContainer.addEventListener('change', (event) => {
    if (event.target.matches('[data-restock-variant]')) refreshAllRows();
    else updateTotalsAndControls();
  });
  linesContainer.addEventListener('input', (event) => {
    if (event.target.matches('[data-restock-quantity], [data-restock-cost]')) updateTotalsAndControls();
  });
  linesContainer.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-restock-line]');
    if (removeButton && linesContainer.querySelectorAll('[data-restock-line]').length > 1) {
      removeButton.closest('[data-restock-line]').remove();
      refreshAllRows();
    }
  });
  form.querySelector('[data-add-restock-line]').addEventListener('click', () => {
    if (linesContainer.querySelectorAll('[data-restock-line]').length >= 50) return;
    const row = lineTemplate.content.firstElementChild.cloneNode(true);
    linesContainer.appendChild(row);
    refreshAllRows();
    row.querySelector('[data-restock-variant]').focus();
  });
  form.querySelector('[data-review-restock]').addEventListener('click', reviewRestock);
  form.querySelector('[data-edit-restock]').addEventListener('click', showEditStep);
  form.addEventListener('submit', (event) => {
    if (confirmedInput.value !== '1') {
      event.preventDefault();
      reviewRestock();
    }
  });
  form.closest('dialog').addEventListener('close', showEditStep);

  refreshAllRows();
  if (form.closest('dialog').hasAttribute('data-open-on-load')) {
    reviewStep.hidden = true;
    editStep.hidden = false;
  }
})();
