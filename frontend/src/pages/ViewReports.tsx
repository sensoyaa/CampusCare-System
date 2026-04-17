import { useEffect, useState } from "react";
import { BarChart3, Users, CalendarDays, Brain, TrendingUp } from "lucide-react";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface StatsData {
  total_users: number;
  appointments_this_month: number;
  assessments_taken: number;
  events_held: number;
}

interface ActivityItem {
  action: string;
  detail: string;
  time: string;
}

const formatRelativeTime = (dateString: string) => {
  const now = new Date();
  const past = new Date(dateString);
  const diffMs = now.getTime() - past.getTime();
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
  const diffDays = Math.floor(diffHours / 24);

  if (diffHours < 1) return "Just now";
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? "s" : ""} ago`;
  return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`;
};

const ViewReports = () => {
  const [stats, setStats] = useState<StatsData>({
    total_users: 0,
    appointments_this_month: 0,
    assessments_taken: 0,
    events_held: 0,
  });

  const [recentActivity, setRecentActivity] = useState<ActivityItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchReports = async () => {
    try {
      const response = await fetch(`${API_BASE}/reports/get_reports.php`);
      const data = await response.json();

      if (data.success) {
        setStats(data.stats);
        setRecentActivity(data.recent_activity || []);
      } else {
        toast.error(data.message || "Failed to load reports.");
      }
    } catch (error) {
      console.error("Fetch reports error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const statsCards = [
    {
      label: "Total Users",
      value: stats.total_users,
      icon: Users,
      change: "Live",
      color: "text-primary",
    },
    {
      label: "Appointments This Month",
      value: stats.appointments_this_month,
      icon: CalendarDays,
      change: "Live",
      color: "text-accent",
    },
    {
      label: "Assessments Taken",
      value: stats.assessments_taken,
      icon: Brain,
      change: "Live",
      color: "text-campus-teal",
    },
    {
      label: "Events This Month",
      value: stats.events_held,
      icon: BarChart3,
      change: "Live",
      color: "text-campus-gold",
    },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-foreground">System Reports</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Overview of system activity and metrics
        </p>
      </div>

      {loading ? (
        <div className="text-center py-12 text-muted-foreground">
          Loading reports...
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {statsCards.map((s) => (
              <div key={s.label} className="bg-card rounded-2xl p-5 shadow-card">
                <div className="flex items-center justify-between mb-3">
                  <s.icon className={`w-5 h-5 ${s.color}`} />
                  <span className="text-xs font-medium text-accent flex items-center gap-1">
                    <TrendingUp className="w-3 h-3" />
                    {s.change}
                  </span>
                </div>
                <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
                <p className="text-sm text-muted-foreground">{s.label}</p>
              </div>
            ))}
          </div>

          <div className="bg-card rounded-2xl p-5 shadow-card">
            <h2 className="font-bold text-foreground mb-4">Recent Activity</h2>
            <div className="space-y-4">
              {recentActivity.length > 0 ? (
                recentActivity.map((a, i) => (
                  <div
                    key={i}
                    className="flex items-start gap-3 pb-4 border-b border-border last:border-0 last:pb-0"
                  >
                    <div className="w-2 h-2 rounded-full bg-primary mt-2 shrink-0" />
                    <div className="flex-1">
                      <p className="text-sm font-medium text-foreground">{a.action}</p>
                      <p className="text-xs text-muted-foreground">{a.detail}</p>
                    </div>
                    <span className="text-xs text-muted-foreground whitespace-nowrap">
                      {formatRelativeTime(a.time)}
                    </span>
                  </div>
                ))
              ) : (
                <div className="text-sm text-muted-foreground">No recent activity found.</div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default ViewReports;

