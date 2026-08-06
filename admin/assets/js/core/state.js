export const state = {
  section: localStorage.getItem("prompt-doom-section") || "dashboard",
  session: JSON.parse(sessionStorage.getItem("prompt-doom-session") || "null"),
  categories: [],
  tags: [],
};

export function saveSession(session) {
  state.session = session;
  if (session) {
    sessionStorage.setItem("prompt-doom-session", JSON.stringify(session));
  } else {
    sessionStorage.removeItem("prompt-doom-session");
  }
}

export function saveSection(section) {
  state.section = section;
  localStorage.setItem("prompt-doom-section", section);
}
