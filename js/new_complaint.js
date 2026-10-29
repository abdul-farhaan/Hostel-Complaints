// New complaint form: character counters and validation before the form is
// sent to submit_complaint.php.

let complaintForm = document.getElementById("complaint-form");
let complaintMessage = document.getElementById("complaint-form-message");
let titleInput = document.getElementById("title");
let descriptionInput = document.getElementById("description");

const maxTitleLength = 100;
const minDescriptionLength = 10;
const maxDescriptionLength = 1000;

function showTitleCount() {
  let remaining = maxTitleLength - titleInput.value.length;
  $("#title-count").text("(" + remaining + " characters left)");
}

function showDescriptionCount() {
  let remaining = maxDescriptionLength - descriptionInput.value.length;
  $("#description-count").text("(" + remaining + " characters left)");
}

function validateComplaintForm(event) {
  let category = $("#category-id").val();
  let title = titleInput.value.trim();
  let location = $("#location").val().trim();
  let priority = $("input[name='priority']:checked").val();
  let description = descriptionInput.value.trim();
  let problem = "";

  if (category === "") {
    problem = "Please choose a category.";
  } else if (title === "") {
    problem = "Please enter a short title.";
  } else if (location === "") {
    problem = "Please enter the location of the problem.";
  } else if (priority === undefined) {
    problem = "Please choose a priority.";
  } else if (description.length < minDescriptionLength) {
    problem = "Please describe the problem in at least " + minDescriptionLength + " characters.";
  }

  // Only stop the submission when something is wrong; a valid form is sent
  // to submit_complaint.php as an ordinary POST request.
  if (problem !== "") {
    event.preventDefault();
    showStatus(complaintMessage, problem, true);
  }
}

complaintForm.addEventListener("submit", validateComplaintForm);
titleInput.addEventListener("input", showTitleCount);
descriptionInput.addEventListener("input", showDescriptionCount);

showTitleCount();
showDescriptionCount();
