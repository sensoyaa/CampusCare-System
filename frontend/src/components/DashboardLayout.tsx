import { SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar";
import { AppSidebar } from "@/components/AppSidebar";
import AIChatbot from "@/components/AIChatbot";
import { Menu } from "lucide-react";

const DashboardLayout = ({ children }: { children: React.ReactNode }) => {
  const storedUser = localStorage.getItem("campuscare_user");
  const parsedUser = storedUser ? JSON.parse(storedUser) : null;

  const userName = parsedUser?.full_name || "Student";

  return (
    <SidebarProvider>
      <div className="min-h-screen flex w-full">
        <AppSidebar />
        <div className="flex-1 flex flex-col min-w-0">
          <header className="h-16 flex items-center justify-between px-4 md:px-8 border-b bg-card">
            <div className="flex items-center gap-3">
              <SidebarTrigger className="text-foreground">
                <Menu className="w-5 h-5" />
              </SidebarTrigger>
            </div>

            <div className="flex items-center gap-3">
              <span className="text-sm text-muted-foreground">Hi, {userName}!</span>
              <div className="w-9 h-9 rounded-full gradient-primary flex items-center justify-center text-primary-foreground font-semibold text-sm">
                {userName.charAt(0).toUpperCase()}
              </div>
            </div>
          </header>

          <main className="flex-1 p-4 md:p-8 overflow-y-auto">
            {children}
          </main>
        </div>
        <AIChatbot />
      </div>
    </SidebarProvider>
  );
};

export default DashboardLayout;