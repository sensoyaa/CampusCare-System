import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Users, CalendarDays, Eye } from "lucide-react";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface StudentItem {
  name: string;
  sessions: number;
  status: string;
}

interface StatsData {
  students_monitored: number;
  available_events: number;
}

const InstructorDashboard = () => {
  const navigate = useNavigate();

  const [stats, setStats] = useState<StatsData>({
    students_monitored: 0,
    available_events: 0,
  });

  const [myStudents, setMyStudents] = useState<StudentItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchInstructorDashboard = async () => {
    try {
      const response = await fetch(`${API_BASE}/dashboards/get_instructor_dashboard.php`);
      const data = await response.json();

      if (data.success) {
        setStats(data.stats);
        setMyStudents(data.student_overview || []);
      } else {
        toast.error(data.message || "Failed to load instructor dashboard.");
      }
    } catch (error) {
      console.error("Instructor dashboard error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInstructorDashboard();
  }, []);

  const quickActions = [
    {
      title: "Student Status",
      icon: Eye,
      path: "/student-status",
      bg: "bg-[hsl(var(--campus-light-blue))]",
      iconBg: "bg-primary",
    },
    {
      title: "View Events",
      icon: CalendarDays,
      path: "/events",
      bg: "bg-[hsl(var(--campus-light-green))]",
      iconBg: "bg-accent",
    },
  ];

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {[
          {
            label: "Students Monitored",
            value: stats.students_monitored,
            color: "text-primary",
            icon: Users,
          },
          {
            label: "Available Events",
            value: stats.available_events,
            color: "text-accent",
            icon: CalendarDays,
          },
        ].map((s) => (
          <div key={s.label} className="bg-card rounded-2xl p-5 shadow-card">
            <s.icon className={`w-5 h-5 ${s.color} mb-2`} />
            <p className={`text-3xl font-bold ${s.color}`}>{loading ? "..." : s.value}</p>
            <p className="text-sm text-muted-foreground">{s.label}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {quickActions.map((action) => (
          <button
            key={action.title}
            onClick={() => navigate(action.path)}
            className={`group ${action.bg} rounded-2xl p-5 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-1 text-left flex flex-col justify-between min-h-[130px]`}
          >
            <div className={`${action.iconBg} w-10 h-10 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform`}>
              <action.icon className="w-5 h-5 text-primary-foreground" />
            </div>
            <span className="font-semibold text-sm text-foreground">{action.title}</span>
          </button>
        ))}
      </div>

      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-foreground">Student Participation Overview</h2>
          <button
            onClick={() => navigate("/student-status")}
            className="text-sm text-primary font-medium hover:underline"
          >
            View All â†’
          </button>
        </div>

        {loading ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            Loading student overview...
          </div>
        ) : myStudents.length === 0 ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            No students found.
          </div>
        ) : (
          <div className="space-y-3">
            {myStudents.map((s, i) => (
              <div
                key={i}
                className="bg-card rounded-2xl p-5 shadow-card flex items-center justify-between"
              >
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-sm font-bold text-primary">
                    {s.name.charAt(0)}
                  </div>
                  <div>
                    <p className="font-semibold text-foreground text-sm">{s.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {s.sessions} counseling sessions
                    </p>
                  </div>
                </div>

                <span
                  className={`text-xs font-semibold px-3 py-1 rounded-full ${
                    s.status === "Active"
                      ? "bg-accent/15 text-accent"
                      : "bg-campus-gold/15 text-campus-gold"
                  }`}
                >
                  {s.status}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
};

export default InstructorDashboard;

