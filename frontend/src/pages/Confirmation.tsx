import { useLocation, useNavigate } from "react-router-dom";
import { CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";

const Confirmation = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const state = location.state as { type?: string; service?: string; counselor?: string; date?: string; time?: string } | null;

  return (
    <div className="max-w-lg mx-auto animate-fade-in">
      <div className="bg-card rounded-2xl p-10 shadow-card text-center">
        <div className="w-24 h-24 rounded-full gradient-primary flex items-center justify-center mx-auto mb-6">
          <CheckCircle2 className="w-12 h-12 text-primary-foreground" />
        </div>
        <h1 className="text-2xl font-bold text-foreground mb-3">Booking Confirmed!</h1>
        <p className="text-muted-foreground mb-6">Your appointment has been successfully scheduled.</p>

        {state && (
          <div className="bg-muted rounded-xl p-5 text-left space-y-2 mb-6">
            {state.service && <p className="text-sm"><span className="font-semibold text-foreground">Service:</span> <span className="text-muted-foreground capitalize">{state.service?.replace("-", " ")}</span></p>}
            {state.counselor && <p className="text-sm"><span className="font-semibold text-foreground">Counselor:</span> <span className="text-muted-foreground">{state.counselor}</span></p>}
            {state.date && <p className="text-sm"><span className="font-semibold text-foreground">Date:</span> <span className="text-muted-foreground">{state.date}</span></p>}
            {state.time && <p className="text-sm"><span className="font-semibold text-foreground">Time:</span> <span className="text-muted-foreground">{state.time}</span></p>}
          </div>
        )}

        <div className="flex gap-3 justify-center">
          <Button onClick={() => navigate("/schedule")} variant="outline" className="rounded-xl">View Schedule</Button>
          <Button onClick={() => navigate("/dashboard")} className="rounded-xl gradient-primary hover:opacity-90">Back to Dashboard</Button>
        </div>
      </div>
    </div>
  );
};

export default Confirmation;
