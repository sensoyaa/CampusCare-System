import { Users, Search } from "lucide-react";
import { useState } from "react";
import { Input } from "@/components/ui/input";

const events = [
  {
    title: "Stress Management Workshop",
    date: "Apr 28, 2025",
    participants: [
      { name: "Maria Santos", id: "STU-2024-001" },
      { name: "Juan Dela Cruz", id: "STU-2024-002" },
      { name: "Lisa Gomez", id: "STU-2024-003" },
    ],
  },
  {
    title: "Mindfulness & Meditation",
    date: "May 5, 2025",
    participants: [
      { name: "Carlo Rivera", id: "STU-2024-004" },
      { name: "Maria Santos", id: "STU-2024-001" },
    ],
  },
];

const ViewParticipants = () => {
  const [search, setSearch] = useState("");

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Event Participants</h1>
        <p className="text-muted-foreground text-sm mt-1">View student attendees for your sessions</p>
      </div>

      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <Input placeholder="Search participants..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10 h-11 rounded-xl" />
      </div>

      <div className="space-y-4">
        {events.map((event, i) => (
          <div key={i} className="bg-card rounded-2xl p-5 shadow-card">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
                <Users className="w-5 h-5 text-primary-foreground" />
              </div>
              <div>
                <p className="font-semibold text-foreground">{event.title}</p>
                <p className="text-xs text-muted-foreground">{event.date} • {event.participants.length} attendees</p>
              </div>
            </div>
            <div className="space-y-2">
              {event.participants
                .filter((p) => p.name.toLowerCase().includes(search.toLowerCase()))
                .map((p) => (
                  <div key={p.id} className="flex items-center gap-3 px-3 py-2 rounded-xl bg-muted/40">
                    <div className="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                      {p.name.charAt(0)}
                    </div>
                    <span className="text-sm font-medium text-foreground">{p.name}</span>
                    <span className="text-xs text-muted-foreground ml-auto font-mono">{p.id}</span>
                  </div>
                ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default ViewParticipants;
