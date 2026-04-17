import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { CalendarDays, Clock, User, Stethoscope, CheckCircle2 } from "lucide-react";
import { toast } from "sonner";

interface CounselorItem {
  id: number;
  full_name: string;
}

const API_BASE = "/campuscare-api/backend";
const timeSlots = ["9:00 AM", "10:00 AM", "11:00 AM", "1:00 PM", "2:00 PM", "3:00 PM"];

const formatTimeForMySQL = (time: string) => {
  const map: Record<string, string> = {
    "9:00 AM": "09:00:00",
    "10:00 AM": "10:00:00",
    "11:00 AM": "11:00:00",
    "1:00 PM": "13:00:00",
    "2:00 PM": "14:00:00",
    "3:00 PM": "15:00:00",
  };
  return map[time] || "09:00:00";
};

const formatDateForMySQL = (date: Date) => {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
};

const formatServiceForBackend = (service: string) => {
  if (service === "counseling") return "Counseling";
  if (service === "psychological-testing") return "Psychological Testing";
  return service;
};

const BookAppointment = () => {
  const navigate = useNavigate();

  const [service, setService] = useState("");
  const [counselorId, setCounselorId] = useState("");
  const [date, setDate] = useState<Date | undefined>();
  const [time, setTime] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [counselors, setCounselors] = useState<CounselorItem[]>([]);

  useEffect(() => {
    const fetchCounselors = async () => {
      try {
        const response = await fetch(`${API_BASE}/appointments/get_active_counselors.php`);
        const data = await response.json();

        if (data.success) {
          setCounselors(data.counselors || []);
        } else {
          toast.error(data.message || "Failed to load counselors.");
        }
      } catch (error) {
        console.error("Fetch counselors error:", error);
        toast.error("Could not load active counselors.");
      }
    };

    fetchCounselors();
  }, []);

  const handleBook = async () => {
    const userId = localStorage.getItem("campuscare_user_id");

    if (!userId) {
      toast.error("User not found. Please log in again.");
      navigate("/");
      return;
    }

    if (!service || !counselorId || !date || !time) {
      toast.error("Please complete all fields.");
      return;
    }

    const selectedCounselor = counselors.find((c) => String(c.id) === counselorId);

    if (!selectedCounselor) {
      toast.error("Selected counselor not found.");
      return;
    }

    try {
      setIsLoading(true);

      const appointmentDate = formatDateForMySQL(date);
      const appointmentTime = formatTimeForMySQL(time);

      const response = await fetch(`${API_BASE}/appointments/add_appointment.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(userId),
          counselor_id: Number(counselorId),
          service: formatServiceForBackend(service),
          counselor: selectedCounselor.full_name,
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success("Appointment booked successfully.");

        navigate("/confirmation", {
          state: {
            type: "appointment",
            service: formatServiceForBackend(service),
            counselor: selectedCounselor.full_name,
            date: date.toLocaleDateString(),
            time,
          },
        });
      } else {
        toast.error(data.message || "Failed to save appointment.");
      }
    } catch (error) {
      console.error("Booking error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setIsLoading(false);
    }
  };

  const steps = [
    { label: "Service", done: !!service },
    { label: "Counselor", done: !!counselorId },
    { label: "Date", done: !!date },
    { label: "Time", done: !!time },
  ];

  return (
    <div className="max-w-3xl mx-auto animate-fade-in">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground mb-1">Book Appointment</h1>
        <p className="text-muted-foreground">Schedule a session with our guidance team</p>
      </div>

      <div className="flex items-center gap-2 mb-8">
        {steps.map((step, i) => (
          <div key={i} className="flex items-center gap-2 flex-1">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
              step.done ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground"
            }`}>
              {step.done ? <CheckCircle2 className="w-4 h-4" /> : i + 1}
            </div>
            <span className={`text-xs font-medium hidden sm:block ${step.done ? "text-primary" : "text-muted-foreground"}`}>
              {step.label}
            </span>
            {i < steps.length - 1 && <div className={`flex-1 h-0.5 rounded ${step.done ? "bg-primary" : "bg-border"}`} />}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="space-y-5">
          <div className="bg-card rounded-2xl p-6 shadow-card space-y-5">
            <div className="space-y-2">
              <Label className="flex items-center gap-2 text-sm font-semibold">
                <Stethoscope className="w-4 h-4 text-primary" /> Service Type
              </Label>
              <Select onValueChange={setService} value={service}>
                <SelectTrigger className="h-12 rounded-xl border-border/60 focus:ring-primary/30">
                  <SelectValue placeholder="Select service" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="counseling">Counseling</SelectItem>
                  <SelectItem value="psychological-testing">Psychological Testing</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label className="flex items-center gap-2 text-sm font-semibold">
                <User className="w-4 h-4 text-primary" /> Counselor
              </Label>
              <Select onValueChange={setCounselorId} value={counselorId}>
                <SelectTrigger className="h-12 rounded-xl border-border/60 focus:ring-primary/30">
                  <SelectValue placeholder="Choose active counselor" />
                </SelectTrigger>
                <SelectContent>
                  {counselors.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.full_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="bg-card rounded-2xl p-6 shadow-card space-y-3">
            <Label className="flex items-center gap-2 text-sm font-semibold">
              <Clock className="w-4 h-4 text-primary" /> Time Slot
            </Label>
            <div className="grid grid-cols-3 gap-2">
              {timeSlots.map((t) => (
                <button
                  key={t}
                  onClick={() => setTime(t)}
                  className={`py-3 rounded-xl text-sm font-medium transition-all border ${
                    time === t
                      ? "bg-primary text-primary-foreground border-primary shadow-md scale-[1.03]"
                      : "bg-muted/50 text-foreground border-border/40 hover:border-primary/40 hover:bg-secondary"
                  }`}
                >
                  {t}
                </button>
              ))}
            </div>
          </div>

          <Button
            onClick={handleBook}
            disabled={!service || !counselorId || !date || !time || isLoading}
            className="w-full h-12 rounded-xl text-base font-semibold gradient-primary hover:opacity-90 transition-opacity shadow-md"
          >
            {isLoading ? "Saving..." : "Confirm Booking"}
          </Button>
        </div>

        <div className="bg-card rounded-2xl p-5 shadow-card h-fit">
          <Label className="flex items-center gap-2 text-sm font-semibold mb-4">
            <CalendarDays className="w-4 h-4 text-primary" /> Select Date
          </Label>
          <Calendar
            mode="single"
            selected={date}
            onSelect={setDate}
            disabled={(d) =>
              d < new Date(new Date().setHours(0, 0, 0, 0)) ||
              d.getDay() === 0 ||
              d.getDay() === 6
            }
            className="rounded-xl w-full [&_.rdp-month]:w-full [&_.rdp-table]:w-full [&_.rdp-head_row]:flex [&_.rdp-head_row]:justify-between [&_.rdp-row]:flex [&_.rdp-row]:justify-between [&_.rdp-cell]:flex-1 [&_.rdp-cell]:flex [&_.rdp-cell]:justify-center [&_.rdp-head_cell]:flex-1 [&_.rdp-head_cell]:text-center [&_.rdp-day]:w-10 [&_.rdp-day]:h-10 [&_.rdp-caption]:pb-3"
          />
          {date && (
            <div className="mt-4 p-3 bg-primary/5 rounded-xl border border-primary/10 text-sm text-foreground">
              Selected:{" "}
              <span className="font-semibold text-primary">
                {date.toLocaleDateString("en-US", {
                  weekday: "long",
                  month: "long",
                  day: "numeric",
                  year: "numeric",
                })}
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default BookAppointment;

