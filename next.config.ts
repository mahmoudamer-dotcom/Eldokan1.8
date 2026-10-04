import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'www.eldokan.com',
      },
    ],
  },
  /* config options here */
};

export default nextConfig;
