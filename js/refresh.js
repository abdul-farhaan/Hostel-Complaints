// Asynchronous status refresh: every 15 seconds (or when "Refresh status now"
// is clicked) the page asks api/statuses.php for the current status of the
// complaints it shows, and updates only the parts that changed. The page is
// never reloaded.

let refreshButton = document.getElementById("refresh-button");
let refreshMessage = document.getElementById("refresh-message");
let refreshTime = document.getElementById("refresh-time");
let newComplaintsNotice = document.getElementById("new-complaints");

const refreshSeconds = 15;
let refreshPending = false;

// The ids of the complaints on this page, e.g. "3,5,9".
function shownComplaintIds() {
  let ids = [];

  $(".tracked-complaint").each(function() {
    ids.push($(this).attr("data-complaint-id"));
  });

  return ids.join(",");
}

function refreshStatuses() {
  // Do not start a second request while the previous one is still running.
  if (refreshPending) {
    return;
  }

  refreshPending = true;
  refreshButton.disabled = true;

  getJson("api/statuses.php?ids=" + shownComplaintIds())
    .then(function(data) {
      let changes = [];

      data.complaints.forEach(function(state) {
        let element = document.getElementById("complaint-" + state.complaintId);

        if (element.getAttribute("data-status") !== state.status) {
          changes.push("#" + state.complaintId + " is now " + state.status);
          highlight(element);
        }

        applyComplaintState(element, state);
      });

      updateSummary(data.summary);

      // A complaint submitted after this page was loaded is not in the list yet.
      if (newComplaintsNotice !== null && data.latestId > Number(newComplaintsNotice.getAttribute("data-latest-id"))) {
        $(newComplaintsNotice).show();
      }

      if (changes.length > 0) {
        let text = "Status changed: complaint " + changes.join(", ") + ".";
        if (document.getElementById("timeline") !== null) {
          text += " Reload the page to see the full history.";
        }
        showStatus(refreshMessage, text, false);
      } else if (refreshMessage.classList.contains("is-error")) {
        // The connection works again, so remove the old error message.
        showStatus(refreshMessage, "", false);
      }

      refreshTime.textContent = "Last checked at " + data.checkedAt + ". Checked again every " + refreshSeconds + " seconds.";
    })
    .catch(function(error) {
      let text = "Could not refresh the status: " + error.message;
      if (!text.endsWith(".")) {
        text += ".";
      }
      showStatus(refreshMessage, text + " Trying again in " + refreshSeconds + " seconds.", true);
    })
    .finally(function() {
      refreshPending = false;
      refreshButton.disabled = false;
    });
}

refreshButton.addEventListener("click", refreshStatuses);
setInterval(refreshStatuses, refreshSeconds * 1000);
