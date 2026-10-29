// Shared by every logged-in page that talks to the api folder.

// The token printed by header.php; the server rejects changes without it.
let csrfToken = $("#csrf-token").val();

// Button text for moving a complaint to its next status (staff only).
const actionLabels = {
  "In Progress": "Start work",
  "Resolved": "Mark resolved"
};

// Reads the JSON reply of one of our endpoints. They always reply with JSON
// containing "ok" and "message", even when they refuse a request, so the reply
// is read first and its message is used as the error text. The reply is also
// attached to the error (error.data) so the caller can still use it.
function readJsonReply(response) {
  return response.json()
    .catch(function() {
      throw new Error("The server sent an unexpected reply (HTTP " + response.status + ").");
    })
    .then(function(data) {
      if (!response.ok || !data.ok) {
        let error = new Error(data.message || "Request failed (HTTP " + response.status + ").");
        error.data = data;
        throw error;
      }
      return data;
    });
}

// GET request: asks for information without changing anything.
function getJson(url) {
  return fetch(url).then(readJsonReply);
}

// POST request: sends a JavaScript object to the server as JSON.
function postJson(url, payload) {
  payload.token = csrfToken;

  return fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  }).then(readJsonReply);
}

// Shows a message in a status paragraph; isError switches its colour.
function showStatus(element, text, isError) {
  element.textContent = text;
  element.classList.toggle("is-error", isError);
}

// Writes the counts returned by the server into the summary tiles (if the page has them).
function updateSummary(summary) {
  $("#summary-total").text(summary.total);
  $("#summary-submitted").text(summary.submitted);
  $("#summary-progress").text(summary.inProgress);
  $("#summary-resolved").text(summary.resolved);
}

// Shows the right staff controls for a complaint: a button for the next
// status, or "Closed" when the complaint is already resolved.
function showStaffActions(element, nextStatus) {
  let actions = $(element).find(".staff-actions");

  if (actions.length === 0) {
    return;
  }

  if (nextStatus === null) {
    actions.find(".remark-input, .move-button").hide();
    actions.find(".no-action").show();
  } else {
    actions.find(".move-button").text(actionLabels[nextStatus]).attr("data-to", nextStatus).show();
    actions.find(".remark-input")
      .attr("placeholder", nextStatus === "Resolved" ? "What was done? (required)" : "Remark for the student (optional)")
      .show();
    actions.find(".no-action").hide();
  }
}

// Changes a complaint card, table row or detail panel to match the state
// sent by the server: { complaintId, status, statusClass, nextStatus, updatedText, lastRemark }.
function applyComplaintState(element, state) {
  element.setAttribute("data-status", state.status);

  let pill = element.querySelector(".status-pill");
  pill.textContent = state.status;
  pill.className = "status-pill " + state.statusClass;

  // Progress bar: steps before the current one are done; the current one is
  // highlighted, unless the complaint is resolved (then every step is done).
  let steps = element.querySelectorAll(".progress-steps .step");
  let current = ["Submitted", "In Progress", "Resolved"].indexOf(state.status);

  for (let i = 0; i < steps.length; i++) {
    steps[i].classList.toggle("step-done", i < current || (i === current && state.nextStatus === null));
    steps[i].classList.toggle("step-current", i === current && state.nextStatus !== null);
  }

  $(element).find(".updated-text").text(state.updatedText);

  if (state.lastRemark !== null) {
    $(element).find(".remark-text").text(state.lastRemark);
    $(element).find(".staff-remark").prop("hidden", false);
  }

  showStaffActions(element, state.nextStatus);
}

// Briefly highlights an element whose status has just changed.
function highlight(element) {
  element.classList.add("just-changed");
  setTimeout(function() {
    element.classList.remove("just-changed");
  }, 3000);
}
