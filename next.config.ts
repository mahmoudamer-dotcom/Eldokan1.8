import type { NextConfig } from "next";

const nextConfig: NextConfig = {
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
    ],
  },
  async headers() {
    return [{ source: '/:path*', headers: [
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
      { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
    ] }, { source: '/:section(account|cart|checkout|orders|wishlist|login|register|search|forgot-password|reset-password)/:path*', headers: [
      { key: 'X-Robots-Tag', value: 'noindex, nofollow' },
    ] }]
  },
};

export default nextConfig;
