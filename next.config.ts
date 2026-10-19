import type { NextConfig } from "next";

// Product images come from the WordPress host. When WordPress moves (for example to
// admin.eldokan.com), ELDOKAN_API_BASE_URL at build time also allows its images.
const wordpressHost = (() => {
  try { return new URL(process.env.ELDOKAN_API_BASE_URL || '').hostname } catch { return '' }
})()

const nextConfig: NextConfig = {
  // Self-contained server (server.js + only the needed node_modules) for Node.js hosting panels.
  output: 'standalone',
  poweredByHeader: false,
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'www.eldokan.com',
      },
      {
        protocol: 'https',
        hostname: 'eldokan.com',
      },
      ...(wordpressHost && !['www.eldokan.com', 'eldokan.com'].includes(wordpressHost) ? [{ protocol: 'https' as const, hostname: wordpressHost }] : []),
    ],
  },
  async headers() {
    return [{ source: '/:path*', headers: [
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
      { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
      // Browsers ignore HSTS over plain HTTP, so local development is unaffected.
      { key: 'Strict-Transport-Security', value: 'max-age=31536000' },
      { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
    ] }, { source: '/:section(account|cart|checkout|orders|invoice|wishlist|login|register|search|forgot-password|reset-password)/:path*', headers: [
      { key: 'X-Robots-Tag', value: 'noindex, nofollow' },
    ] }]
  },
};

export default nextConfig;
