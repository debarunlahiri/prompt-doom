import { saveSession, state } from "./state.js";

const API_BASE = window.PROMPT_DOOM_API;

export async function api(path, options = {}, retry = true) {
  const headers = {
    ...(options.body instanceof FormData
      ? {}
      : { "Content-Type": "application/json" }),
    ...options.headers,
  };
  if (state.session?.tokens?.accessToken) {
    headers.Authorization = `Bearer ${state.session.tokens.accessToken}`;
  }

  const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
  if (response.status === 204) return null;
  const payload = await response.json().catch(() => ({
    success: false,
    error: { message: "Invalid API response" },
  }));

  if (
    response.status === 401 &&
    retry &&
    state.session?.tokens?.refreshToken &&
    !path.includes("/auth/")
  ) {
    try {
      const renewed = await api(
        "/auth/refresh",
        {
          method: "POST",
          body: JSON.stringify({
            refreshToken: state.session.tokens.refreshToken,
          }),
        },
        false,
      );
      saveSession({ ...state.session, tokens: renewed.tokens });
      return api(path, options, false);
    } catch {
      saveSession(null);
      window.dispatchEvent(new Event("prompt-doom-session-expired"));
      throw new Error("Your session has expired.");
    }
  }
  if (!response.ok || payload.success === false)
    throw new Error(payload.error?.message || "Request failed");
  return payload.data;
}

export async function login(email, password) {
  return api(
    "/auth/admin/login",
    { method: "POST", body: JSON.stringify({ email, password }) },
    false,
  );
}

export async function logout() {
  if (state.session?.tokens?.refreshToken) {
    await api(
      "/auth/logout",
      {
        method: "POST",
        body: JSON.stringify({
          refreshToken: state.session.tokens.refreshToken,
        }),
      },
      false,
    );
  }
  saveSession(null);
}
