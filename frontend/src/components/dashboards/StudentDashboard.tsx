import { useNavigate } from "react-router-dom";
import { CalendarPlus, CalendarDays, Brain, Users, ArrowRight, Bell } from "lucide-react";
import { format } from "date-fns";

const quickActions = [
  { title: "Book Counseling", icon: CalendarPlus, path: "/book-appointment", bg: "bg-[hsl(var(--campus-light-blue))]", iconBg: "bg-primary" },
  { title: "Join Workshops", icon: Users, path: "/events", bg: "bg-[hsl(var(--campus-light-green))]", iconBg: "bg-accent" },
  { title: "Mental Health Test", icon: Brain, path: "/mental-health-test", bg: "bg-secondary", iconBg: "bg-campus-teal" },
  { title: "My Schedule", icon: CalendarDays, path: "/schedule", bg: "bg-[hsl(var(--campus-light-blue))]", iconBg: "bg-campus-gold" },
];

const announcements = [
  { title: "Mental Health Week", date: "Apr 28 - May 3", dot: "bg-campus-gold" },
  { title: "Resume Workshop", date: "Apr 27, 2PM", dot: "bg-primary" },
  { title: "Student Org Fair", date: "May 5, 10AM", dot: "bg-accent" },
];

const upcomingAppointments = [
  { title: "Counseling with Dr. Santos", date: "Tue, April 30 | 10:00 AM", location: "Guidance Office", status: "Approved" },
  { title: "Psychological Testing", date: "Thu, May 2 | 2:00 PM", location: "Room 204", status: "Pending" },
];

const StudentDashboard = () => {
  const navigate = useNavigate();

  return (
    <>
      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          { label: "Upcoming Events", value: "3", sub: "this week", color: "text-primary" },
          { label: "Pending Requests", value: "2", sub: "new messages", color: "text-accent" },
          { label: "Your Apps", value: "1", sub: "for approval", color: "text-campus-gold" },
        ].map((card) => (
          <div key={card.label} className="bg-card rounded-2xl p-5 shadow-card hover:shadow-card-hover transition-shadow">
            <p className="text-sm text-muted-foreground mb-1">{card.label}</p>
            <div className="flex items-end justify-between">
              <div>
                <span className={`text-3xl font-bold ${card.color}`}>{card.value}</span>
                <span className="text-sm text-muted-foreground ml-2">{card.sub}</span>
              </div>
              <ArrowRight className="w-4 h-4 text-muted-foreground" />
            </div>
          </div>
        ))}
      </div>

      {/* Quick Access + Announcements */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <h2 className="text-lg font-bold text-foreground mb-4">Quick Access</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {quickActions.map((action) => (
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

        <div className="bg-card rounded-2xl p-5 shadow-card">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Bell className="w-4 h-4 text-primary" />
              <h3 className="font-bold text-foreground">Announcements</h3>
            </div>
          </div>
          <div className="space-y-4">
            {announcements.map((a, i) => (
              <div key={i} className="flex items-start gap-3">
                <div className={`w-2.5 h-2.5 rounded-full ${a.dot} mt-1.5 shrink-0`} />
                <div>
                  <p className="font-medium text-sm text-foreground">{a.title}</p>
                  <p className="text-xs text-muted-foreground">{a.date}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Upcoming Appointments */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-foreground">Upcoming Appointments</h2>
          <button onClick={() => navigate("/schedule")} className="text-sm text-primary font-medium hover:underline">View All →</button>
        </div>
        <div className="space-y-3">
          {upcomingAppointments.map((apt, i) => (
            <div key={i} className="bg-card rounded-2xl p-5 shadow-card flex items-center justify-between animate-slide-in" style={{ animationDelay: `${i * 100}ms` }}>
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
                  <CalendarDays className="w-5 h-5 text-primary-foreground" />
                </div>
                <div>
                  <p className="font-semibold text-card-foreground">{apt.title}</p>
                  <p className="text-sm text-muted-foreground">{apt.date} • {apt.location}</p>
                </div>
              </div>
              <span className={`text-xs font-semibold px-3 py-1 rounded-full ${apt.status === "Approved" ? "bg-secondary text-secondary-foreground" : "bg-[hsl(var(--campus-light-blue))] text-primary"}`}>
                {apt.status}
              </span>
            </div>
          ))}
        </div>
      </div>
    </>
  );
};

export default StudentDashboard;
