import { useEffect, useState } from "react";
import { CalendarDays, MapPin, Plus, Trash2, Check, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "sonner";

type EventCategory = "Brownbag" | "Wellness" | "Training";

const categoryColors: Record<EventCategory, string> = {
  Brownbag: "bg-primary text-primary-foreground",
  Wellness: "bg-accent text-accent-foreground",
  Training: "bg-campus-teal text-campus-teal-foreground",
};

interface EventItem {
  id: number;
  title: string;
  event_date: string;
  event_time: string;
  location: string;
  category: EventCategory;
}

const API_BASE = "/campuscare-api/backend";

const formatEventDateTime = (date: string, time: string) => {
  const dateObj = new Date(`${date}T${time}`);
  return dateObj.toLocaleString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
};

const Events = () => {
  const [events, setEvents] = useState<EventItem[]>([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);

  const [formData, setFormData] = useState({
    title: "",
    event_date: "",
    event_time: "",
    location: "",
    category: "Brownbag" as EventCategory,
  });

  const role = localStorage.getItem("campuscare_role") || "Student";
  const canManage = ["Administrator", "Facilitator"].includes(role);

  const fetchEvents = async () => {
    try {
      const response = await fetch(`${API_BASE}/events/get_events.php`);
      const data = await response.json();

      if (data.success) {
        const mappedEvents: EventItem[] = (data.events || []).map((event: any) => ({
          id: event.id,
          title: event.title,
          event_date: event.event_date,
          event_time: event.event_time,
          location: event.location,
          category: (event.description as EventCategory) || "Brownbag",
        }));

        setEvents(mappedEvents);
      } else {
        toast.error(data.message || "Failed to load events.");
      }
    } catch (error) {
      console.error("Fetch events error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEvents();
  }, []);

  const addEvent = async () => {
    if (
      !formData.title.trim() ||
      !formData.event_date.trim() ||
      !formData.event_time.trim() ||
      !formData.location.trim()
    ) {
      toast.error("Please fill in all fields");
      return;
    }

    try {
      setCreating(true);

      const response = await fetch(`${API_BASE}/events/add_event.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          title: formData.title,
          event_date: formData.event_date,
          event_time: formData.event_time,
          location: formData.location,
          description: formData.category,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success("Event created", { description: formData.title });
        setDialogOpen(false);
        setFormData({
          title: "",
          event_date: "",
          event_time: "",
          location: "",
          category: "Brownbag",
        });
        fetchEvents();
      } else {
        toast.error(data.message || "Failed to create event.");
      }
    } catch (error) {
      console.error("Add event error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setCreating(false);
    }
  };

  const deleteEvent = (id: number) => {
    const ev = events.find((e) => e.id === id);
    setEvents((prev) => prev.filter((e) => e.id !== id));
    setDeleteConfirm(null);
    toast.success(`"${ev?.title}" removed`);
  };

  return (
    <div className="max-w-3xl mx-auto animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <h1 className="text-2xl font-bold text-foreground">Brown Bag Sessions</h1>
        {canManage && (
          <Button
            className="gradient-primary text-primary-foreground rounded-xl gap-2"
            onClick={() => setDialogOpen(true)}
          >
            <Plus className="w-4 h-4" /> Create Event
          </Button>
        )}
      </div>

      {loading ? (
        <div className="text-center py-12 text-muted-foreground">
          Loading events...
        </div>
      ) : (
        <div className="space-y-4">
          {events.map((event, i) => (
            <div
              key={event.id}
              className="bg-card rounded-2xl p-6 shadow-card hover:shadow-card-hover transition-all animate-slide-in"
              style={{ animationDelay: `${i * 60}ms` }}
            >
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 rounded-xl bg-secondary flex items-center justify-center flex-shrink-0">
                  <CalendarDays className="w-6 h-6 text-primary" />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-3">
                    <h3 className="font-bold text-card-foreground text-lg">{event.title}</h3>

                    <div className="flex items-center gap-2">
                      <span
                        className={`text-xs font-semibold px-3 py-1 rounded-full flex-shrink-0 ${
                          categoryColors[event.category]
                        }`}
                      >
                        {event.category}
                      </span>

                      {canManage && (
                        deleteConfirm === event.id ? (
                          <div className="flex gap-1">
                            <button
                              onClick={() => deleteEvent(event.id)}
                              className="p-1.5 rounded-lg bg-destructive/10 hover:bg-destructive/20"
                            >
                              <Check className="w-3.5 h-3.5 text-destructive" />
                            </button>
                            <button
                              onClick={() => setDeleteConfirm(null)}
                              className="p-1.5 rounded-lg hover:bg-muted"
                            >
                              <X className="w-3.5 h-3.5 text-muted-foreground" />
                            </button>
                          </div>
                        ) : (
                          <button
                            onClick={() => setDeleteConfirm(event.id)}
                            className="p-1.5 rounded-lg hover:bg-destructive/10"
                          >
                            <Trash2 className="w-3.5 h-3.5 text-destructive" />
                          </button>
                        )
                      )}
                    </div>
                  </div>

                  <div className="mt-2 space-y-1 text-sm text-muted-foreground">
                    <p className="flex items-center gap-2">
                      <CalendarDays className="w-4 h-4" />
                      {formatEventDateTime(event.event_date, event.event_time)}
                    </p>
                    <p className="flex items-center gap-2">
                      <MapPin className="w-4 h-4" />
                      {event.location}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          ))}

          {events.length === 0 && (
            <div className="text-center py-12 text-muted-foreground">
              <CalendarDays className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p>No events yet.</p>
            </div>
          )}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="rounded-2xl">
          <DialogHeader>
            <DialogTitle>Create New Event</DialogTitle>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Title</Label>
              <Input
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                placeholder="Event title"
                className="rounded-xl"
              />
            </div>

            <div className="space-y-2">
              <Label>Date</Label>
              <Input
                type="date"
                value={formData.event_date}
                onChange={(e) => setFormData({ ...formData, event_date: e.target.value })}
                className="rounded-xl"
              />
            </div>

            <div className="space-y-2">
              <Label>Time</Label>
              <Input
                type="time"
                value={formData.event_time}
                onChange={(e) => setFormData({ ...formData, event_time: e.target.value })}
                className="rounded-xl"
              />
            </div>

            <div className="space-y-2">
              <Label>Location</Label>
              <Input
                value={formData.location}
                onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                placeholder="e.g. Room 201"
                className="rounded-xl"
              />
            </div>

            <div className="space-y-2">
              <Label>Category</Label>
              <Select
                value={formData.category}
                onValueChange={(v) =>
                  setFormData({ ...formData, category: v as EventCategory })
                }
              >
                <SelectTrigger className="rounded-xl">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Brownbag">Brownbag</SelectItem>
                  <SelectItem value="Wellness">Wellness</SelectItem>
                  <SelectItem value="Training">Training</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDialogOpen(false)}
              className="rounded-xl"
            >
              Cancel
            </Button>
            <Button
              onClick={addEvent}
              disabled={creating}
              className="rounded-xl gradient-primary text-primary-foreground"
            >
              {creating ? "Creating..." : "Create Event"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default Events;

