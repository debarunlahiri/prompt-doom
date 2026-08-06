export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [
  ...root.querySelectorAll(selector),
];
export const icon = (name) => `<i data-lucide="${name}"></i>`;
export const escapeHtml = (value) =>
  String(value ?? "").replace(
    /[&<>'"]/g,
    (character) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[
        character
      ],
  );
export const formatDate = (value) =>
  value
    ? new Intl.DateTimeFormat("en", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(new Date(value.replace(" ", "T")))
    : "Never";
export const refreshIcons = () =>
  window.lucide?.createIcons({ attrs: { "stroke-width": 1.8 } });

export function hero(title, description, action = "") {
  return `<div class="hero"><div><span class="eyebrow">PROMPT DOOM ADMIN</span><h2>${escapeHtml(title)}</h2><p>${escapeHtml(description)}</p></div>${action}</div>`;
}

export function panel(content, className = "") {
  return `<section class="panel${className ? ` ${className}` : ""}">${content}</section>`;
}

export function panelHeader(title, description, action = "") {
  return `<div class="panel-head"><div><h2>${escapeHtml(title)}</h2><p>${escapeHtml(description)}</p></div>${action}</div>`;
}

export function filterBar(fields) {
  return `<div class="toolbar">${fields}</div>`;
}

export function dataTable(headers, body, className = "", bodyId = "") {
  return `<div class="table-wrap${className ? ` ${className}` : ""}"><table><thead><tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join("")}</tr></thead><tbody${bodyId ? ` id="${escapeHtml(bodyId)}"` : ""}>${body}</tbody></table></div>`;
}

export function emptyTableRow(columnCount, message) {
  return `<tr><td class="table-empty" colspan="${columnCount}">${icon("inbox")}<span>${escapeHtml(message)}</span></td></tr>`;
}

export function empty(title, description) {
  return `<div class="empty">${icon("blocks")}<h3>${escapeHtml(title)}</h3><p>${escapeHtml(description)}</p></div>`;
}

export function loading(label = "Loading") {
  return `<div class="empty">${icon("loader-circle")}<h3>${escapeHtml(label)}</h3></div>`;
}

export function toast(message, type = "success") {
  $("#toast-root").innerHTML =
    `<div class="toast ${type}">${escapeHtml(message)}</div>`;
  setTimeout(() => {
    $("#toast-root").innerHTML = "";
  }, 3500);
}

export function openModal(title, content, className = "") {
  $("#modal-root").innerHTML =
    `<div class="modal-backdrop"><div class="modal${className ? ` ${escapeHtml(className)}` : ""}"><div class="modal-head"><h2>${escapeHtml(title)}</h2><button class="icon-button" data-close-modal aria-label="Close">${icon("x")}</button></div>${content}</div></div>`;
  $$(
    '.modal input:not([type="file"]):not([type="checkbox"]):not([type="radio"]):not([type="range"])',
  ).forEach((control) => control.classList.add("form-control"));
  $$(".modal select").forEach((control) =>
    control.classList.add("select-control"),
  );
  $$(".modal textarea").forEach((control) =>
    control.classList.add("textarea-control"),
  );
  $$("[data-close-modal]").forEach((button) => {
    button.onclick = closeModal;
  });
  refreshIcons();
}

export function closeModal() {
  $("#modal-root").innerHTML = "";
}

export function eventBars(events) {
  if (!events.length)
    return empty(
      "No activity yet",
      "Recorded event activity will appear here.",
    );
  const maximum = Math.max(...events.map((event) => Number(event._count)), 1);
  return `<div class="events">${events.map((event) => `<div class="event-row"><span>${escapeHtml(event.eventType.replaceAll("_", " "))}</span><div class="bar"><span style="width:${Math.max((event._count / maximum) * 100, 4)}%"></span></div><strong>${Number(event._count).toLocaleString()}</strong></div>`).join("")}</div>`;
}
