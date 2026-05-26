// BookSys PWA Service Worker (minimal — install 가능하게만)
// 캐시 전략을 추가하려면 여기에서 fetch 이벤트 핸들러 확장.

const VERSION = 'booksys-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// 네트워크 우선 (오프라인 캐시 X — 운영 데이터 항상 fresh)
self.addEventListener('fetch', (event) => {
    // 기본 동작 (브라우저가 처리)
});
