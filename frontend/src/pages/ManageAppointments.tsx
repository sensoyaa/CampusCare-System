import { useEffect, useState } from "react";
import { CalendarDays, Search, CheckCircle2, Clock, XCircle } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface Appointment {
  id: number;
  student: string;
  counselor: string;
  appointment_date: string;
  appointment_time: string;
  type: string;
  status: string;
}

const statusConfig: Record<string, { icon: any; class: string }> = {
  Approved: { icon: CheckCircle2, class: "text-accent bg-accent/15" },
  Pending: { icon: Clock, class: "text-primary bg-primary/15" },
  Cancelled: { icon: XCircle, class: "text-destructive bg-destructive/15" },
  Rejected: { icon: XCircle, class: "text-destructive bg-destructive/15" },
};

const formatDateTime = (date: string, time: string) => {
  const dateObj = new Date(`${date}T${time}`);
  return dateObj.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
};

const ManageAppointments = () => {
  const [search, setSearch] = useState("");
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAppointments = async () => {
    try {
      const response = await fetch(`${API_BASE}/appointments/get_all_appointments.php`);
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

  const filtered = appointments.filter(
    (a) =>
      a.student.toLowerCase().includes(search.toLowerCase()) ||
      a.counselor.toLowerCase().includes(search.toLowerCase())
  );

  const updateStatus = async (id: number, newStatus: string) => {
    try {
      const response = await fetch(`${API_BASE}/appointments/update_appointment_status.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id,
          status: newStatus,
        }),
      });

      const data = await response.json();

      if (data.success) {
        setAppointments((prev) =>
          prev.map((a) => (a.id === id ? { ...a, status: newStatus } : a))
        );

        const apt = appointments.find((a) => a.id === id);
        toast.success(`${apt?.student}'s appointment ${newStatus.toLowerCase()}`, {
          description: `${apt?.type} on ${apt ? formatDateTime(apt.appointment_date, apt.appointment_time) : ""}`,
        });
      } else {
        toast.error(data.message || "Failed to update appointment.");
      }
    } catch (error) {
      console.error("Update status error:", error);
      toast.error("Could not connect to the server.");
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Manage Appointments</h1>
        <p className="text-muted-foreground text-sm mt-1">View and control all bookings</p>
      </div>

      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <Input
          placeholder="Search by student or counselor..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10 h-11 rounded-xl"
        />
      </div>

      {loading ? (
        <div className="text-center py-12 text-muted-foreground">Loading appointments...</div>
      ) : (
        <div className="space-y-3">
          {filtered.map((apt) => {
            const sc = statusConfig[apt.status] || statusConfig["Pending"];
            const Icon = sc.icon;

            return (
              <div
                key={apt.id}
                className="bg-card rounded-2xl p-5 shadow-card flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              >
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
                    <CalendarDays className="w-5 h-5 text-primary-foreground" />
                  </div>
                  <div>
                    <p className="font-semibold text-foreground">{apt.student}</p>
                    <p className="text-sm text-muted-foreground">
                      {apt.type} â€¢ {apt.counselor} â€¢ {formatDateTime(apt.appointment_date, apt.appointment_time)}
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <span className={`text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1.5 ${sc.class}`}>
                    <Icon className="w-3.5 h-3.5" /> {apt.status}
                  </span>

                  {apt.status === "Pending" && (
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        className="rounded-lg gradient-primary text-primary-foreground text-xs h-8"
                        onClick={() => updateStatus(apt.id, "Approved")}
                      >
                        Approve
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        className="rounded-lg text-xs h-8"
                        onClick={() => updateStatus(apt.id, "Cancelled")}
                      >
                        Decline
                      </Button>
                    </div>
                  )}

                  {apt.status === "Approved" && (
                    <Button
                      size="sm"
                      variant="outline"
                      className="rounded-lg text-xs h-8 text-destructive border-destructive/30 hover:bg-destructive/10"
                      onClick={() => updateStatus(apt.id, "Cancelled")}
                    >
                      Cancel
                    </Button>
                  )}
                </div>
              </div>
            );
          })}

          {filtered.length === 0 && (
            <div className="text-center py-12 text-muted-foreground">
              <CalendarDays className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p>No appointments found.</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default ManageAppointments;

