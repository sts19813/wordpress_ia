import fs from "node:fs";
import { chromium } from "playwright-core";

const url = process.argv[2];
const timeout = Number.parseInt(process.env.SOCIAL_BROWSER_TIMEOUT_MS || "45000", 10);

if (!url) {
    throw new Error("Falta la URL de la publicación.");
}

const remoteEndpoint = process.env.SOCIAL_BROWSER_WS_ENDPOINT;
const executableCandidates = [
    process.env.SOCIAL_BROWSER_EXECUTABLE,
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge",
    "/Applications/Chromium.app/Contents/MacOS/Chromium",
    "/usr/bin/google-chrome",
    "/usr/bin/google-chrome-stable",
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
].filter(Boolean);

const executablePath = executableCandidates.find((candidate) => fs.existsSync(candidate));

if (!remoteEndpoint && !executablePath) {
    throw new Error("No se encontró un navegador. Configura SOCIAL_BROWSER_EXECUTABLE o SOCIAL_BROWSER_WS_ENDPOINT.");
}

const browser = remoteEndpoint
    ? await chromium.connectOverCDP(remoteEndpoint, { timeout })
    : await chromium.launch({
        headless: true,
        executablePath,
        args: [
            "--disable-dev-shm-usage",
            "--disable-notifications",
            "--lang=es-MX",
            "--no-sandbox",
        ],
    });

try {
    const page = await browser.newPage({
        locale: "es-MX",
        viewport: { width: 1440, height: 1200 },
        userAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36",
    });

    const allowedNavigationDomains = [
        "facebook.com",
        "fb.watch",
        "x.com",
        "twitter.com",
        "t.co",
        "instagram.com",
    ];

    await page.route("**/*", async (route) => {
        const request = route.request();

        if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
            const host = new URL(request.url()).hostname.toLowerCase();
            const allowed = allowedNavigationDomains.some(
                (domain) => host === domain || host.endsWith(`.${domain}`),
            );

            if (!allowed) {
                await route.abort("blockedbyclient");
                return;
            }
        }

        await route.continue();
    });

    const response = await page.goto(url, {
        waitUntil: "domcontentloaded",
        timeout,
    });

    await page.waitForTimeout(3500);

    // Some galleries only load the remaining assets after a small scroll.
    await page.evaluate(() => window.scrollTo(0, Math.min(document.body.scrollHeight, 1200)));
    await page.waitForTimeout(1200);

    const discoveredImages = new Map();
    const collectImages = async () => {
        const images = await page.locator("img").evaluateAll((elements) => elements
            .map((image) => ({
                url: image.currentSrc || image.src,
                alt: image.alt || null,
                width: image.naturalWidth || null,
                height: image.naturalHeight || null,
            }))
            .filter((image) => /^https?:\/\//i.test(image.url))
            .filter((image) => (image.width || 0) >= 300 && (image.height || 0) >= 200));

        images.forEach((image) => discoveredImages.set(image.url, image));
    };

    await collectImages();

    // Instagram and some Facebook galleries replace the current image instead
    // of keeping every slide in the DOM. Walk the public carousel and retain
    // every large image that becomes visible.
    for (let slide = 0; slide < 20; slide += 1) {
        const nextButton = page.locator([
            'main button[aria-label="Next"]',
            'main button[aria-label="Siguiente"]',
            'main [role="button"][aria-label="Next"]',
            'main [role="button"][aria-label="Siguiente"]',
        ].join(", ")).filter({ visible: true }).first();

        if (await nextButton.count() === 0 || await nextButton.isDisabled().catch(() => false)) {
            break;
        }

        const previousCount = discoveredImages.size;

        try {
            await nextButton.click({ timeout: 1500 });
            await page.waitForTimeout(700);
            await collectImages();
        } catch {
            break;
        }

        if (discoveredImages.size === previousCount) {
            break;
        }
    }

    const result = await page.evaluate(() => {
        const meta = {};

        document.querySelectorAll("meta[property], meta[name]").forEach((element) => {
            const key = element.getAttribute("property") || element.getAttribute("name");
            const value = element.getAttribute("content");

            if (key && value && /^(og:|twitter:|description$)/i.test(key)) {
                meta[key] = value;
            }
        });

        const jsonLd = Array.from(document.querySelectorAll('script[type="application/ld+json"]'))
            .map((script) => script.textContent?.trim())
            .filter(Boolean)
            .map((value) => value.slice(0, 50_000))
            .slice(0, 10);

        return {
            canonical_url: document.querySelector('link[rel="canonical"]')?.href || window.location.href,
            final_url: window.location.href,
            title: document.title,
            text: (document.body?.innerText?.trim() || "").slice(0, 100_000),
            html_language: document.documentElement.lang || null,
            meta,
            json_ld: jsonLd,
        };
    });

    result.images = Array.from(discoveredImages.values()).slice(0, 30);
    result.http_status = response?.status() || null;
    process.stdout.write(JSON.stringify(result));
} finally {
    await browser.close();
}
