import type { Metadata } from "next";
import "./globals.css";
import { ThemeProvider } from "@/providers/theme-provider";
import { QueryProvider } from "@/providers/query-provider";
import { AuthProvider } from "@/providers/auth-provider";
import { Navbar } from "@/components/layout/Navbar";

export const metadata: Metadata = {
  title: "School ERP SaaS - Multi-Tenant School Management System",
  description: "Commercial multi-tenant School ERP SaaS platform for modern education institutions.",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" suppressHydrationWarning className="h-full">
      <body className="min-h-full flex flex-col bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 transition-colors">
        <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
          <QueryProvider>
            <AuthProvider>
              <Navbar />
              <main className="flex-1">{children}</main>
              <footer className="border-t border-slate-200 py-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <div className="container mx-auto px-4">
                  School ERP SaaS Platform &copy; 2026 &mdash; Enterprise Multi-Tenant Architecture
                </div>
              </footer>
            </AuthProvider>
          </QueryProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
