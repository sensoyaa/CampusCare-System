import { CalendarDays } from "lucide-react";
import { format } from "date-fns";
import StudentDashboard from "@/components/dashboards/StudentDashboard";
import AdminDashboard from "@/components/dashboards/AdminDashboard";
import CounselorDashboard from "@/components/dashboards/CounselorDashboard";
import FacilitatorDashboard from "@/components/dashboards/FacilitatorDashboard";
import InstructorDashboard from "@/components/dashboards/InstructorDashboard";

const roleDashboards: Record<string, React.FC> = {
  Student: StudentDashboard,
  Administrator: AdminDashboard,
  Counsellor: CounselorDashboard,
  Facilitator: FacilitatorDashboard,
  Instructor: InstructorDashboard,
};

const Dashboard = () => {
  const storedUser = localStorage.getItem("campuscare_user");
  const parsedUser = storedUser ? JSON.parse(storedUser) : null;

  const userName = parsedUser?.full_name || "User";
  const role =
    parsedUser?.role ||
    localStorage.getItem("campuscare_role") ||
    "Student";

  const today = format(new Date(), "MMMM d, yyyy");
  const RoleDashboard = roleDashboards[role] || StudentDashboard;

  return (
    <div className="space-y-8 animate-fade-in">
      <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
          <h1 className="text-2xl md:text-3xl font-bold text-foreground">
            Welcome back, {userName}!
          </h1>
          <p className="text-muted-foreground mt-1">
            Logged in as{" "}
            <span className="font-semibold text-primary">{role}</span> — Here's
            what's happening today.
          </p>
        </div>

        <div className="bg-card rounded-2xl px-5 py-3 shadow-card flex items-center gap-3 shrink-0">
          <CalendarDays className="w-5 h-5 text-primary" />
          <div className="text-right">
            <p className="text-xs text-muted-foreground">Today</p>
            <p className="font-semibold text-sm text-foreground">{today}</p>
          </div>
        </div>
      </div>

      <RoleDashboard />
    </div>
  );
};

export default Dashboard;