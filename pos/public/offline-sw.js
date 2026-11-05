/* Cache only the public shell and immutable Vite assets. No API/session/PII caching. */
const CACHE = "pos-offline-shell-v1";
self.addEventListener("install", (event) =>
  event.waitUntil(
    (async () => {
      const response = await fetch("/offline/app", {
        cache: "no-store",
        credentials: "omit",
      });
      if (!response.ok) throw new Error("Offline shell unavailable");
      const html = await response.clone().text();
      const urls = [...html.matchAll(/(?:src|href)="([^"]+)"/g)]
        .map((match) => new URL(match[1], self.location.origin))
        .filter(
          (url) =>
            url.origin === self.location.origin &&
            /^\/build\/assets\/[A-Za-z0-9_.-]+\.(?:js|css)$/.test(
              url.pathname,
            ) &&
            !url.search,
        )
        .map((url) => url.pathname);
      if (!urls.some((url) => url.endsWith(".js")))
        throw new Error("Offline shell has no immutable script");
      const cache = await caches.open(CACHE);
      await cache.put("/offline/app", response);
      await cache.addAll([...new Set(urls)]);
    })(),
  ),
);
self.addEventListener("activate", (event) =>
  event.waitUntil(
    (async () => {
      for (const key of await caches.keys())
        if (key.startsWith("pos-offline-shell-") && key !== CACHE)
          await caches.delete(key);
      await self.clients.claim();
    })(),
  ),
);
self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== "GET" || url.origin !== self.location.origin)
    return;
  if (url.pathname === "/offline/app") {
    event.respondWith(
      fetch(event.request).catch(async () =>
        (await caches.open(CACHE)).match("/offline/app"),
      ),
    );
  } else if (
    /^\/build\/assets\/[A-Za-z0-9_.-]+\.(js|css|woff2?)$/.test(url.pathname) &&
    !url.search
  ) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(CACHE);
        const stored = await cache.match(event.request);
        if (stored) return stored;
        const response = await fetch(event.request);
        if (response.ok && response.type === "basic")
          await cache.put(event.request, response.clone());
        return response;
      })(),
    );
  }
});
