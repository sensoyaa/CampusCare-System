import { useState } from "react";
import { MessageSquare, Send, User, CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";

interface Session {
  id: number;
  student: string;
  date: string;
  type: string;
  notes: string;
  saved: boolean;
}

const initialSessions: Session[] = [
  { id: 1, student: "Maria Santos", date: "Apr 28, 2025", type: "Counseling", notes: "Discussed academic stress and coping strategies.", saved: true },
  { id: 2, student: "Juan Dela Cruz", date: "Apr 25, 2025", type: "Follow-up", notes: "", saved: false },
  { id: 3, student: "Lisa Gomez", date: "Apr 22, 2025", type: "Psychological Testing", notes: "Completed anxiety assessment. Recommend follow-up.", saved: true },
];

const ProvideFeedback = () => {
  const [sessions, setSessions] = useState<Session[]>(initialSessions);
  const [activeId, setActiveId] = useState<number | null>(null);
  const [noteText, setNoteText] = useState("");

  const openEdit = (session: Session) => {
    setActiveId(activeId === session.id ? null : session.id);
    setNoteText(session.notes);
  };

  const saveNotes = (id: number) => {
    setSessions((prev) =>
      prev.map((s) => (s.id === id ? { ...s, notes: noteText, saved: true } : s))
    );
    setActiveId(null);
    toast.success("Session notes saved", {
      description: `Notes for ${sessions.find((s) => s.id === id)?.student} updated.`,
    });
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Session Feedback</h1>
        <p className="text-muted-foreground text-sm mt-1">Record notes for completed sessions</p>
      </div>

      <div className="space-y-3">
        {sessions.map((s) => (
          <div key={s.id} className="bg-card rounded-2xl p-5 shadow-card">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center">
                  <User className="w-4 h-4 text-primary" />
                </div>
                <div>
                  <p className="font-semibold text-foreground text-sm">{s.student}</p>
                  <p className="text-xs text-muted-foreground">{s.type} • {s.date}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                {s.saved && s.notes && (
                  <span className="text-xs text-accent flex items-center gap-1">
                    <CheckCircle2 className="w-3 h-3" /> Saved
                  </span>
                )}
                <button
                  onClick={() => openEdit(s)}
                  className="text-xs text-primary font-medium hover:underline flex items-center gap-1"
                >
                  <MessageSquare className="w-3.5 h-3.5" />
                  {s.notes ? "Edit Notes" : "Add Notes"}
                </button>
              </div>
            </div>
            {s.notes && activeId !== s.id && (
              <p className="text-sm text-muted-foreground bg-muted/50 rounded-xl p-3">{s.notes}</p>
            )}
            {activeId === s.id && (
              <div className="space-y-3 mt-2">
                <Textarea
                  value={noteText}
                  onChange={(e) => setNoteText(e.target.value)}
                  placeholder="Write session notes..."
                  className="rounded-xl min-h-[100px]"
                />
                <div className="flex justify-end gap-2">
                  <Button variant="outline" size="sm" className="rounded-lg" onClick={() => setActiveId(null)}>Cancel</Button>
                  <Button size="sm" className="rounded-lg gradient-primary text-primary-foreground gap-1.5" onClick={() => saveNotes(s.id)}>
                    <Send className="w-3.5 h-3.5" /> Save Notes
                  </Button>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default ProvideFeedback;
