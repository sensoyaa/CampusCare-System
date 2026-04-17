import { LayoutDashboard, CalendarPlus, CalendarDays, Brain, Users, LogOut, UserPlus, BarChart3, Clock, MessageSquare, Eye } from "lucide-react";
import { NavLink } from "@/components/NavLink";
import { useNavigate } from "react-router-dom";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarFooter,
  useSidebar,
} from "@/components/ui/sidebar";

type NavItem = { title: string; url: string; icon: any };

const roleNavItems: Record<string, NavItem[]> = {
  Student: [
    { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
    { title: "Book Appointment", url: "/book-appointment", icon: CalendarPlus },
    { title: "My Schedule", url: "/schedule", icon: CalendarDays },
    { title: "Mental Health Test", url: "/mental-health-test", icon: Brain },
    { title: "Brown Bag Sessions", url: "/events", icon: Users },
  ],
  Administrator: [
    { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
    { title: "Manage Users", url: "/manage-users", icon: UserPlus },
    { title: "Manage Appointments", url: "/manage-appointments", icon: CalendarDays },
    { title: "Manage Events", url: "/events", icon: Users },
    { title: "View Reports", url: "/reports", icon: BarChart3 },
  ],
  Counselors: [
    { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
    { title: "View Appointments", url: "/schedule", icon: CalendarDays },
    { title: "Manage Schedule", url: "/manage-schedule", icon: Clock },
    { title: "Session Feedback", url: "/provide-feedback", icon: MessageSquare },
  ],
  Facilitator: [
    { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
    { title: "Manage Events", url: "/events", icon: Users },
    { title: "View Participants", url: "/view-participants", icon: Eye },
  ],
  Instructor: [
    { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
    { title: "Student Status", url: "/student-status", icon: Eye },
    { title: "View Events", url: "/events", icon: CalendarDays },
  ],
};

export function AppSidebar() {
  const { state } = useSidebar();
  const collapsed = state === "collapsed";
  const navigate = useNavigate();
  const role = localStorage.getItem("campuscare_role") || "Student";
  const items = roleNavItems[role] || roleNavItems.Student;

  const handleLogout = () => {
    localStorage.removeItem("campuscare_user");
    localStorage.removeItem("campuscare_role");
    navigate("/");
  };

  return (
    <Sidebar collapsible="icon" className="border-r-0">
      <div className="p-5 flex items-center gap-3 mb-2">
        <img src="/images/logo.png" alt="CampusCare" className="w-10 h-10 flex-shrink-0" />
        {!collapsed && (
          <div className="flex flex-col">
            <span className="font-bold text-lg text-sidebar-foreground leading-tight">CampusCare</span>
            <span className="text-[10px] text-sidebar-foreground/60 tracking-wide">Balanced. Supported. Thriving.</span>
          </div>
        )}
      </div>
      <SidebarContent className="px-3 mt-2">
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu className="space-y-1.5">
              {items.map((item) => (
                <SidebarMenuItem key={item.title}>
                  <SidebarMenuButton asChild>
                    <NavLink
                      to={item.url}
                      end
                      className="rounded-xl px-4 py-3 transition-colors hover:bg-sidebar-accent"
                      activeClassName="bg-sidebar-accent font-semibold"
                    >
                      <item.icon className="w-5 h-5 mr-3 flex-shrink-0" />
                      {!collapsed && <span>{item.title}</span>}
                    </NavLink>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter className="px-4 pb-4">
        {!collapsed && (
          <div className="px-3 py-2 mb-2 text-xs text-sidebar-foreground/50">
            Role: <span className="font-semibold text-sidebar-foreground/80">{role}</span>
          </div>
        )}
        <button onClick={handleLogout} className="flex items-center gap-3 text-sidebar-foreground/70 hover:text-sidebar-foreground transition-colors w-full px-3 py-2.5 rounded-xl hover:bg-sidebar-accent">
          <LogOut className="w-5 h-5 flex-shrink-0" />
          {!collapsed && <span className="text-sm">Log Out</span>}
        </button>
      </SidebarFooter>
    </Sidebar>
  );
}
