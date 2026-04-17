import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import DashboardLayout from "@/components/DashboardLayout";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import BookAppointment from "./pages/BookAppointment";
import Schedule from "./pages/Schedule";
import MentalHealthTest from "./pages/MentalHealthTest";
import Events from "./pages/Events";
import Confirmation from "./pages/Confirmation";
import ManageUsers from "./pages/ManageUsers";
import ManageAppointments from "./pages/ManageAppointments";
import ViewReports from "./pages/ViewReports";
import ManageSchedule from "./pages/ManageSchedule";
import ProvideFeedback from "./pages/ProvideFeedback";
import ViewParticipants from "./pages/ViewParticipants";
import ViewStudentStatus from "./pages/ViewStudentStatus";
import NotFound from "./pages/NotFound";

const queryClient = new QueryClient();
const routerBase = import.meta.env.BASE_URL;

const WithLayout = ({ children }: { children: React.ReactNode }) => (
  <DashboardLayout>{children}</DashboardLayout>
);

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter basename={routerBase}>
        <Routes>
          <Route path="/" element={<Login />} />
          <Route path="/dashboard" element={<WithLayout><Dashboard /></WithLayout>} />
          <Route path="/book-appointment" element={<WithLayout><BookAppointment /></WithLayout>} />
          <Route path="/schedule" element={<WithLayout><Schedule /></WithLayout>} />
          <Route path="/mental-health-test" element={<WithLayout><MentalHealthTest /></WithLayout>} />
          <Route path="/events" element={<WithLayout><Events /></WithLayout>} />
          <Route path="/confirmation" element={<WithLayout><Confirmation /></WithLayout>} />
          <Route path="/manage-users" element={<WithLayout><ManageUsers /></WithLayout>} />
          <Route path="/manage-appointments" element={<WithLayout><ManageAppointments /></WithLayout>} />
          <Route path="/reports" element={<WithLayout><ViewReports /></WithLayout>} />
          <Route path="/manage-schedule" element={<WithLayout><ManageSchedule /></WithLayout>} />
          <Route path="/provide-feedback" element={<WithLayout><ProvideFeedback /></WithLayout>} />
          <Route path="/view-participants" element={<WithLayout><ViewParticipants /></WithLayout>} />
          <Route path="/student-status" element={<WithLayout><ViewStudentStatus /></WithLayout>} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
