import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Users, CalendarDays, Plus } from "lucide-react";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface EventItem {
  id: number;
  title: string;
  date: string;
  participants: number;
}

interface StatsData {
  your_events: number;
  total_participants: number;
  this_week: number;
}

const FacilitatorDashboard = () => {
  const navigate = useNavigate();

  const [stats, setStats] = useState<StatsData>({
    your_events: 0,
    total_participants: 0,
    this_week: 0,
  });

  const [upcomingEvents, setUpcomingEvents] = useState<EventItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchFacilitatorDashboard = async () => {
    try {
      const response = await fetch(`${API_BASE}/dashboards/get_facilitator_dashboard.php`);
      const data = await response.json();

      if (data.success) {
        setStats(data.stats);
        setUpcomingEvents(data.upcoming_events || []);
      } else {
        toast.error(data.message || "Failed to load facilitator dashboard.");
      }
    } catch (error) {
      console.error("Facilitator dashboard error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFacilitatorDashboard();
  }, []);

  const quickActions = [
    {
      title: "Manage Events",
      icon: CalendarDays,
      path: "/events",
      bg: "bg-[hsl(var(--campus-light-blue))]",
      iconBg: "bg-primary",
    },
    {
      title: "View Participants",
      icon: Users,
      path: "/view-participants",
      bg: "bg-[hsl(var(--campus-light-green))]",
      iconBg: "bg-accent",
    },
    {
      title: "Create Session",
      icon: Plus,
      path: "/events",
      bg: "bg-secondary",
      iconBg: "bg-campus-gold",
    },
  ];

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          { label: "Your Events", value: stats.your_events, color: "text-primary", icon: CalendarDays },
          { label: "Total Participants", value: stats.total_participants, color: "text-accent", icon: Users },
          { label: "This Week", value: stats.this_week, color: "text-campus-gold", icon: CalendarDays },
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
        <h2 className="text-lg font-bold text-foreground mb-4">Upcoming Sessions</h2>

        {loading ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            Loading sessions...
          </div>
        ) : upcomingEvents.length === 0 ? (
          <div className="bg-card rounded-2xl p-5 shadow-card text-muted-foreground">
            No upcoming sessions found.
          </div>
        ) : (
          <div className="space-y-3">
            {upcomingEvents.map((e) => (
              <div key={e.id} className="bg-card rounded-2xl p-5 shadow-card flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
                    <CalendarDays className="w-5 h-5 text-primary-foreground" />
                  </div>
                  <div>
                    <p className="font-semibold text-foreground">{e.title}</p>
                    <p className="text-sm text-muted-foreground">{e.date}</p>
                  </div>
                </div>
                <span className="text-xs font-semibold px-3 py-1 rounded-full bg-secondary text-secondary-foreground">
                  {e.participants} attendees
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
};

export default FacilitatorDashboard;

