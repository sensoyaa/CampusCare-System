import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { CalendarDays, Clock, MessageSquare, User } from "lucide-react";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface AppointmentItem {
  student: string;
  time: string;
  type: string;
  status: string;
}

interface StatsData {
  today_sessions: number;
  pending_notes: number;
  this_week: number;
}

const CounselorDashboard = () => {
  const navigate = useNavigate();

  const [stats, setStats] = useState<StatsData>({
    today_sessions: 0,
    pending_notes: 0,
    this_week: 0,
  });

  const [todayAppointments, setTodayAppointments] = useState<AppointmentItem[]>([]);
  const [loading, setLoading] = useState(true);

 const counselorId = localStorage.getItem("campuscare_user_id");

const fetchCounselorDashboard = async () => {
  try {
    const response = await fetch(
      `${API_BASE}/dashboards/get_counselor_dashboard.php?counselor_id=${counselorId}`
    );

      const data = await response.json();

      if (data.success) {
        setStats(data.stats);
        setTodayAppointments(data.today_appointments || []);
      } else {
        toast.error(data.message || "Failed to load counselor dashboard.");
      }
    } catch (error) {
      console.error("Counselor dashboard error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCounselorDashboard();
  }, []);

  const quickActions = [
    {
      title: "View Appointments",
      icon: CalendarDays,
      path: "/manage-appointments",
      bg: "bg-[hsl(var(--campus-light-blue))]",
      iconBg: "bg-primary",
    },
    {
      title: "Manage Schedule",
      icon: Clock,
      path: "/schedule",
      bg: "bg-[hsl(var(--campus-light-green))]",
      iconBg: "bg-accent",
    },
    {
      title: "Session Feedback",
      icon: MessageSquare,
      path: "/provide-feedback",
      bg: "bg-secondary",
      iconBg: "bg-campus-gold",
    },
  ];

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          {
            label: "Today's Sessions",
            value: stats.today_sessions,
            color: "text-primary",
            icon: CalendarDays,
          },
          {
            label: "Pending Notes",
            value: stats.pending_notes,
            color: "text-campus-gold",
            icon: MessageSquare,
          },
          {
            label: "This Week",
            value: stats.this_week,
            color: "text-accent",
            icon: Clock,
          },
        ].map((s) => (
          <div key={s.label} className="bg-card rounded-2xl p-5 shadow-card">
            <s.icon className={`w-5 h-5 ${s.color} mb-2`} />
            <p className={`text-3xl font-bold ${s.color}`}>{loading ? "..." : s.value}</p>
            <p className="text-sm text-muted-foreground">{s.label}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
        <h2 className="text-lg font-bold text-foreground mb-4">Today's Appointments</h2>

        {loading ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            Loading appointments...
          </div>
        ) : todayAppointments.length === 0 ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            No appointments for today.
          </div>
        ) : (
          <div className="space-y-3">
            {todayAppointments.map((apt, i) => (
              <div key={i} className="bg-card rounded-2xl p-5 shadow-card flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center">
                    <User className="w-4 h-4 text-primary" />
                  </div>
                  <div>
                    <p className="font-semibold text-foreground">{apt.student}</p>
                    <p className="text-sm text-muted-foreground">
                      {apt.type} â€¢ {apt.time}
                    </p>
                  </div>
                </div>

                <span
                  className={`text-xs font-semibold px-3 py-1 rounded-full ${
                    apt.status === "Approved"
                      ? "bg-accent/15 text-accent"
                      : apt.status === "Cancelled"
                      ? "bg-destructive/15 text-destructive"
                      : "bg-primary/15 text-primary"
                  }`}
                >
                  {apt.status}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
};

export default CounselorDashboard;

