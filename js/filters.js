// Search and filter form: choosing a different option in any drop-down list
// applies the filter straight away (the form is sent as a GET request).

let filterForm = document.getElementById("filter-form");

function submitFilters() {
  filterForm.submit();
}

$("#filter-form select").on("change", submitFilters);
