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

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    cache: "no-store",
  });
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

export function upload(path, body, onProgress = () => {}) {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open("POST", `${API_BASE}${path}`);
    if (state.session?.tokens?.accessToken) {
      request.setRequestHeader(
        "Authorization",
        `Bearer ${state.session.tokens.accessToken}`,
      );
    }
    request.responseType = "json";
    request.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };
    request.onerror = () =>
      reject(new Error("Upload failed. Check your connection and try again."));
    request.onload = () => {
      const payload = request.response;
      if (
        request.status < 200 ||
        request.status >= 300 ||
        payload?.success === false
      ) {
        reject(
          new Error(
            payload?.error?.message || `Upload failed (${request.status}).`,
          ),
        );
        return;
      }
      onProgress(100);
      resolve(payload?.data);
    };
    request.send(body);
  });
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
