import { useEffect, useState } from "react";
import { CalendarDays, MapPin, XCircle, Check, X } from "lucide-react";
import { toast } from "sonner";

interface Appointment {
  id: number;
  service: string;
  counselor: string;
  appointment_date: string;
  appointment_time: string;
  status: string;
}

const API_BASE = "/campuscare-api/backend";

const formatDateTime = (date: string, time: string) => {
  const dateObj = new Date(`${date}T${time}`);
  return dateObj.toLocaleString("en-US", {
    weekday: "short",
    month: "long",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
};

const Schedule = () => {
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [cancelConfirm, setCancelConfirm] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  const userId = localStorage.getItem("campuscare_user_id");

  const fetchAppointments = async () => {
    if (!userId) {
      toast.error("User not found. Please log in again.");
      setLoading(false);
      return;
    }

    try {
      const response = await fetch(
        `${API_BASE}/appointments/get_appointments.php?user_id=${userId}`
      );
      const data = await response.json();

      if (data.success) {
        setAppointments(data.appointments || []);
      } else {
        toast.error(data.message || "Failed to load appointments.");
      }
    } catch (error) {
      console.error("Fetch appointments error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAppointments();
  }, []);

  const cancelAppointment = (id: number) => {
    const apt = appointments.find((a) => a.id === id);

    setAppointments((prev) =>
      prev.map((a) =>
        a.id === id ? { ...a, status: "Cancelled" } : a
      )
    );

    setCancelConfirm(null);
    toast.success("Appointment cancelled", {
      description: apt ? `${apt.service} with ${apt.counselor}` : "Updated",
    });
  };

  return (
    <div className="max-w-2xl mx-auto animate-fade-in">
      <h1 className="text-2xl font-bold text-foreground mb-2">My Schedule</h1>
      <p className="text-muted-foreground mb-8">Your upcoming appointments</p>

      {loading ? (
        <div className="bg-card rounded-2xl p-6 shadow-card text-center text-muted-foreground">
          Loading appointments...
        </div>
      ) : appointments.length === 0 ? (
        <div className="bg-card rounded-2xl p-6 shadow-card text-center text-muted-foreground">
          No appointments found.
        </div>
      ) : (
        <div className="space-y-4">
          {appointments.map((apt, i) => (
            <div
              key={apt.id}
              className="bg-card rounded-2xl p-6 shadow-card hover:shadow-card-hover transition-all animate-slide-in"
              style={{ animationDelay: `${i * 80}ms` }}
            >
              <div className="flex items-start justify-between">
                <div className="flex gap-4">
                  <div className="w-12 h-12 rounded-xl gradient-primary flex items-center justify-center flex-shrink-0">
                    <CalendarDays className="w-6 h-6 text-primary-foreground" />
                  </div>

                  <div>
                    <h3 className="font-semibold text-card-foreground">
                      {apt.service} with {apt.counselor}
                    </h3>
                    <p className="text-sm text-muted-foreground mt-1">
                      {formatDateTime(apt.appointment_date, apt.appointment_time)}
                    </p>
                    <p className="text-sm text-muted-foreground flex items-center gap-1 mt-1">
                      <MapPin className="w-3.5 h-3.5" /> Guidance Office
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <span
                    className={`text-xs font-semibold px-3 py-1.5 rounded-full flex-shrink-0 ${
                      apt.status === "Approved"
                        ? "bg-accent/15 text-accent"
                        : apt.status === "Cancelled"
                        ? "bg-destructive/15 text-destructive"
                        : "bg-primary/15 text-primary"
                    }`}
                  >
                    {apt.status}
                  </span>

                  {apt.status !== "Cancelled" && (
                    cancelConfirm === apt.id ? (
                      <div className="flex gap-1">
                        <button
                          onClick={() => cancelAppointment(apt.id)}
                          className="p-1.5 rounded-lg bg-destructive/10 hover:bg-destructive/20"
                          title="Confirm cancel"
                        >
                          <Check className="w-3.5 h-3.5 text-destructive" />
                        </button>
                        <button
                          onClick={() => setCancelConfirm(null)}
                          className="p-1.5 rounded-lg hover:bg-muted"
                          title="Keep appointment"
                        >
                          <X className="w-3.5 h-3.5 text-muted-foreground" />
                        </button>
                      </div>
                    ) : (
                      <button
                        onClick={() => setCancelConfirm(apt.id)}
                        className="p-1.5 rounded-lg hover:bg-destructive/10 transition-colors"
                        title="Cancel appointment"
                      >
                        <XCircle className="w-4 h-4 text-destructive/60" />
                      </button>
                    )
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default Schedule;

