// Service Worker para Cache Avançado
const CACHE_NAME = 'massangos-cache-v1';
const STATIC_CACHE = 'massangos-static-v1';
const DYNAMIC_CACHE = 'massangos-dynamic-v1';

// Recursos para cache estático
const STATIC_ASSETS = [
    '/',
    '/assets/css/style.css',
    '/assets/css/perfil.css',
    '/assets/js/main.js',
    '/assets/js/image-optimization.js',
    '/assets/js/performance-optimizer.js',
    '/uploads/default_profile.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'
];

// Estratégias de cache
const CACHE_STRATEGIES = {
    images: 'cache-first',
    api: 'network-first',
    static: 'cache-first',
    dynamic: 'stale-while-revalidate'
};

// Instalação do Service Worker
self.addEventListener('install', event => {
    console.log('Service Worker: Instalando...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Service Worker: Cache estático criado');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Recursos estáticos em cache');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Erro na instalação', error);
            })
    );
});

// Ativação do Service Worker
self.addEventListener('activate', event => {
    console.log('Service Worker: Ativando...');

    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Service Worker: Removendo cache antigo', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Ativado');
                return self.clients.claim();
            })
    );
});

// Interceptação de requisições
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorar requisições não-HTTP
    if (!request.url.startsWith('http')) {
        return;
    }

    // Estratégia baseada no tipo de recurso
    if (isProtectedMediaRequest(request)) {
        // Mídia protegida SEMPRE deve vir da rede para garantir validação de token e marca d'água
        event.respondWith(fetch(request));
    } else if (isImageRequest(request)) {
        event.respondWith(cacheFirstStrategy(request, DYNAMIC_CACHE));
    } else if (isAPIRequest(request)) {
        event.respondWith(networkFirstStrategy(request, DYNAMIC_CACHE));
    } else if (isStaticAsset(request)) {
        event.respondWith(cacheFirstStrategy(request, STATIC_CACHE));
    } else {
        event.respondWith(staleWhileRevalidateStrategy(request, DYNAMIC_CACHE));
    }
});

// Estratégia Cache First
async function cacheFirstStrategy(request, cacheName) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('Cache First Strategy falhou:', error);
        return new Response('Recurso não disponível offline', { status: 503 });
    }
}

// Estratégia Network First
async function networkFirstStrategy(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('Network falhou, tentando cache:', error);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        return new Response('Recurso não disponível', { status: 503 });
    }
}

// Estratégia Stale While Revalidate
async function staleWhileRevalidateStrategy(request, cacheName) {
    const cachedResponse = await caches.match(request);

    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            const cache = caches.open(cacheName);
            cache.then(c => c.put(request, networkResponse.clone()));
        }
        return networkResponse;
    }).catch(error => {
        console.log('Network falhou:', error);
        return cachedResponse;
    });

    return cachedResponse || fetchPromise;
}

// Verificadores de tipo de requisição
function isProtectedMediaRequest(request) {
    return request.url.includes('media-proxy.php') ||
        request.url.includes('/media-proxy/');
}

function isImageRequest(request) {
    return (request.destination === 'image' ||
        request.url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i)) &&
        !isProtectedMediaRequest(request);
}

function isAPIRequest(request) {
    return request.url.includes('/api/') ||
        request.url.includes('process_') ||
        request.method !== 'GET';
}

function isStaticAsset(request) {
    return request.url.match(/\.(css|js|woff|woff2|ttf|eot)$/i) ||
        STATIC_ASSETS.includes(new URL(request.url).pathname);
}

// Limpeza periódica do cache
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'CLEAN_CACHE') {
        cleanOldCache();
    }
});

async function cleanOldCache() {
    const cache = await caches.open(DYNAMIC_CACHE);
    const requests = await cache.keys();
    const now = Date.now();
    const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 dias

    for (const request of requests) {
        const response = await cache.match(request);
        const dateHeader = response.headers.get('date');
        if (dateHeader) {
            const responseDate = new Date(dateHeader).getTime();
            if (now - responseDate > maxAge) {
                await cache.delete(request);
                console.log('Cache limpo:', request.url);
            }
        }
    }
}

// Limpeza automática a cada 24 horas
setInterval(cleanOldCache, 24 * 60 * 60 * 1000);

