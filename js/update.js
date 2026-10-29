// Staff update interface: moves a complaint to its next status
// (Submitted -> In Progress -> Resolved) with fetch and JSON, without reloading the page.

let updateMessage = document.getElementById("update-message");

// Adds the new status change to the history list on the complaint details page.
function addHistoryEntry(entry) {
  let timeline = $("#timeline");

  if (timeline.length === 0) {
    return;
  }

  let item = $("<li>");
  item.append($("<strong>").text(entry.oldStatus + " → " + entry.newStatus));
  item.append($("<span>").addClass("muted").text(" by " + entry.byName + ", " + entry.timeText));

  if (entry.remark !== null) {
    item.append($("<p>").addClass("timeline-remark").text(entry.remark));
  }

  timeline.append(item);
}

function moveComplaint() {
  let button = this;
  let complaintId = Number(button.getAttribute("data-complaint-id"));
  let element = document.getElementById("complaint-" + complaintId);
  let remarkInput = element.querySelector(".remark-input");
  let fromStatus = element.getAttribute("data-status");
  let toStatus = button.getAttribute("data-to");
  let remark = remarkInput.value.trim();

  if (toStatus === "Resolved" && remark === "") {
    showStatus(updateMessage, "Complaint #" + complaintId + ": describe the work done before marking it resolved.", true);
    remarkInput.focus();
    return;
  }

  // Prevent a second click from sending a competing request.
  button.disabled = true;
  remarkInput.disabled = true;
  showStatus(updateMessage, "Updating complaint #" + complaintId + "...", false);

  postJson("api/update_status.php", {
    complaintId: complaintId,
    fromStatus: fromStatus,
    toStatus: toStatus,
    remark: remark
  })
    .then(function(data) {
      applyComplaintState(element, data.complaint);
      highlight(element);
      remarkInput.value = "";
      updateSummary(data.summary);
      addHistoryEntry(data.historyEntry);
      showStatus(updateMessage, data.message, false);
    })
    .catch(function(error) {
      // If someone else changed the complaint first, the server sends its
      // current state, so the page can show it.
      if (error.data && error.data.complaint) {
        applyComplaintState(element, error.data.complaint);
        highlight(element);
      }
      showStatus(updateMessage, "Could not update complaint #" + complaintId + ": " + error.message, true);
    })
    .finally(function() {
      button.disabled = false;
      remarkInput.disabled = false;
    });
}

$(".move-button").on("click", moveComplaint);
