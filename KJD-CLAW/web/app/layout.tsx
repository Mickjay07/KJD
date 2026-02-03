import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { ThemeProvider } from "@/components/theme-provider";

import { Navbar } from "@/components/layout/Navbar";
import { Footer } from "@/components/layout/Footer";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" });

export const metadata: Metadata = {
  title: "Kubaja Designs | Prémiový 3D tisk a osvětlení",
  description: "Unikátní 3D tištěné lampy a doplňky inspirované přírodou.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="cs" suppressHydrationWarning>
      <body className={`${inter.variable} font-sans antialiased selection:bg-kjd-earthGreen selection:text-white bg-kjd-lightBeige dark:bg-kjd-darkGreen text-kjd-darkGreen dark:text-kjd-lightBeige transition-colors duration-500`}>
        <ThemeProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
            disableTransitionOnChange
          >
            <div className="flex flex-col min-h-screen">
              <Navbar />
              <main className="flex-grow pt-20">
                {children}
              </main>
              <Footer />
            </div>
          </ThemeProvider>
      </body>
    </html>
  );
}
