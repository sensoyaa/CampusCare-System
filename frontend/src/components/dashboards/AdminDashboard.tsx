import { useNavigate } from "react-router-dom";
import { Users, CalendarDays, BarChart3, ArrowRight, UserPlus, Settings } from "lucide-react";

const AdminDashboard = () => {
  const navigate = useNavigate();

  const stats = [
    { label: "Total Users", value: "1,245", color: "text-primary", icon: Users },
    { label: "Today's Appointments", value: "12", color: "text-accent", icon: CalendarDays },
    { label: "Pending Approvals", value: "5", color: "text-campus-gold", icon: Settings },
    { label: "Events This Month", value: "8", color: "text-campus-teal", icon: BarChart3 },
  ];

  const quickLinks = [
    { title: "Manage Users", icon: UserPlus, path: "/manage-users", bg: "bg-[hsl(var(--campus-light-blue))]", iconBg: "bg-primary" },
    { title: "Manage Appointments", icon: CalendarDays, path: "/manage-appointments", bg: "bg-[hsl(var(--campus-light-green))]", iconBg: "bg-accent" },
    { title: "Manage Events", icon: Users, path: "/events", bg: "bg-secondary", iconBg: "bg-campus-teal" },
    { title: "View Reports", icon: BarChart3, path: "/reports", bg: "bg-[hsl(var(--campus-light-blue))]", iconBg: "bg-campus-gold" },
  ];

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((s) => (
          <div key={s.label} className="bg-card rounded-2xl p-5 shadow-card">
            <s.icon className={`w-5 h-5 ${s.color} mb-2`} />
            <p className={`text-3xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-sm text-muted-foreground">{s.label}</p>
          </div>
        ))}
      </div>

      <div>
        <h2 className="text-lg font-bold text-foreground mb-4">Quick Actions</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {quickLinks.map((action) => (
            <button
              key={action.title}
              onClick={() => navigate(action.path)}
              className={`group ${action.bg} rounded-2xl p-5 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-1 text-left flex flex-col justify-between min-h-[140px]`}
            >
              <div className={`${action.iconBg} w-10 h-10 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform`}>
                <action.icon className="w-5 h-5 text-primary-foreground" />
              </div>
              <div className="flex items-end justify-between w-full">
                <span className="font-semibold text-sm text-foreground">{action.title}</span>
                <ArrowRight className="w-4 h-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
              </div>
            </button>
          ))}
        </div>
      </div>
    </>
  );
};

export default AdminDashboard;
