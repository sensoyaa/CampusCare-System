import { Search, CheckCircle2, Clock, AlertCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

interface StudentItem {
  name: string;
  id: string;
  sessions: number;
  lastVisit: string;
  status: string;
}

const statusConfig: Record<string, { icon: any; class: string }> = {
  Active: { icon: CheckCircle2, class: "text-accent bg-accent/15" },
  "Follow-up": { icon: Clock, class: "text-campus-gold bg-campus-gold/15" },
  "No sessions": { icon: AlertCircle, class: "text-muted-foreground bg-muted" },
};

const ViewStudentStatus = () => {
  const [search, setSearch] = useState("");
  const [students, setStudents] = useState<StudentItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchStudentStatus = async () => {
    try {
      const response = await fetch(`${API_BASE}/users/get_student_status.php`);
      const data = await response.json();

      if (data.success) {
        setStudents(data.students || []);
      } else {
        toast.error(data.message || "Failed to load student status.");
      }
    } catch (error) {
      console.error("Student status error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStudentStatus();
  }, []);

  const filtered = students.filter(
    (s) =>
      s.name.toLowerCase().includes(search.toLowerCase()) ||
      s.id.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Student Participation</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Check student counseling participation and event attendance
        </p>
      </div>

      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <Input
          placeholder="Search student..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10 h-11 rounded-xl"
        />
      </div>

      <div className="bg-card rounded-2xl shadow-card overflow-hidden">
        <div className="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] gap-4 px-5 py-3 bg-muted/50 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
          <span>Student</span>
          <span>ID</span>
          <span>Sessions</span>
          <span>Last Visit</span>
          <span>Status</span>
        </div>

        {loading ? (
          <div className="text-center py-8 text-muted-foreground text-sm">
            Loading students...
          </div>
        ) : filtered.length > 0 ? (
          filtered.map((s) => {
            const sc = statusConfig[s.status];
            const Icon = sc.icon;

            return (
              <div
                key={s.id}
                className="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] gap-4 px-5 py-4 border-t border-border items-center hover:bg-muted/30 transition-colors"
              >
                <span className="text-sm font-medium text-foreground">{s.name}</span>
                <span className="text-sm font-mono text-muted-foreground">{s.id}</span>
                <span className="text-sm text-foreground">{s.sessions}</span>
                <span className="text-sm text-muted-foreground">{s.lastVisit}</span>
                <span className={`text-xs font-semibold px-2.5 py-1 rounded-full w-fit flex items-center gap-1 ${sc.class}`}>
                  <Icon className="w-3 h-3" /> {s.status}
                </span>
              </div>
            );
          })
        ) : (
          <div className="text-center py-8 text-muted-foreground text-sm">
            No users found.
          </div>
        )}
      </div>
    </div>
  );
};

export default ViewStudentStatus;

