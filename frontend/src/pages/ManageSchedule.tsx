import { useState } from "react";
import { Clock, Plus, Trash2, X, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";

const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
const timeSlots = ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "1:00 PM", "2:00 PM", "3:00 PM", "4:00 PM", "5:00 PM"];

const ManageSchedule = () => {
  const [availability, setAvailability] = useState([
    { id: 1, day: "Monday", from: "9:00 AM", to: "11:00 AM" },
    { id: 2, day: "Wednesday", from: "1:00 PM", to: "4:00 PM" },
    { id: 3, day: "Friday", from: "10:00 AM", to: "3:00 PM" },
  ]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [newSlot, setNewSlot] = useState({ day: "Monday", from: "9:00 AM", to: "11:00 AM" });
  const [deleteConfirm, setDeleteConfirm] = useState<number | null>(null);
  let nextId = availability.length ? Math.max(...availability.map((a) => a.id)) + 1 : 1;

  const addSlot = () => {
    if (newSlot.from === newSlot.to) {
      toast.error("Start and end times cannot be the same");
      return;
    }
    setAvailability((prev) => [...prev, { id: nextId, ...newSlot }]);
    toast.success(`Availability added for ${newSlot.day}`, { description: `${newSlot.from} — ${newSlot.to}` });
    setDialogOpen(false);
  };

  const removeSlot = (id: number) => {
    const slot = availability.find((a) => a.id === id);
    setAvailability((prev) => prev.filter((a) => a.id !== id));
    setDeleteConfirm(null);
    toast.success(`${slot?.day} slot removed`);
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Manage Schedule</h1>
          <p className="text-muted-foreground text-sm mt-1">Set your availability for student appointments</p>
        </div>
        <Button className="gradient-primary text-primary-foreground rounded-xl gap-2" onClick={() => setDialogOpen(true)}>
          <Plus className="w-4 h-4" /> Add Slot
        </Button>
      </div>

      <div className="space-y-3">
        {availability.map((slot) => (
          <div key={slot.id} className="bg-card rounded-2xl p-5 shadow-card flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
                <Clock className="w-5 h-5 text-primary-foreground" />
              </div>
              <div>
                <p className="font-semibold text-foreground">{slot.day}</p>
                <p className="text-sm text-muted-foreground">{slot.from} — {slot.to}</p>
              </div>
            </div>
            {deleteConfirm === slot.id ? (
              <div className="flex gap-2">
                <button onClick={() => removeSlot(slot.id)} className="p-2 rounded-lg bg-destructive/10 hover:bg-destructive/20 transition-colors">
                  <Check className="w-4 h-4 text-destructive" />
                </button>
                <button onClick={() => setDeleteConfirm(null)} className="p-2 rounded-lg hover:bg-muted transition-colors">
                  <X className="w-4 h-4 text-muted-foreground" />
                </button>
              </div>
            ) : (
              <button onClick={() => setDeleteConfirm(slot.id)} className="p-2 rounded-lg hover:bg-destructive/10 transition-colors">
                <Trash2 className="w-4 h-4 text-destructive" />
              </button>
            )}
          </div>
        ))}
      </div>

      {availability.length === 0 && (
        <div className="text-center py-12 text-muted-foreground">
          <Clock className="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>No availability slots set. Click "Add Slot" to get started.</p>
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="rounded-2xl">
          <DialogHeader>
            <DialogTitle>Add Availability Slot</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Day</Label>
              <Select value={newSlot.day} onValueChange={(v) => setNewSlot({ ...newSlot, day: v })}>
                <SelectTrigger className="rounded-xl"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {days.map((d) => <SelectItem key={d} value={d}>{d}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>From</Label>
                <Select value={newSlot.from} onValueChange={(v) => setNewSlot({ ...newSlot, from: v })}>
                  <SelectTrigger className="rounded-xl"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {timeSlots.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>To</Label>
                <Select value={newSlot.to} onValueChange={(v) => setNewSlot({ ...newSlot, to: v })}>
                  <SelectTrigger className="rounded-xl"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {timeSlots.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} className="rounded-xl">Cancel</Button>
            <Button onClick={addSlot} className="rounded-xl gradient-primary text-primary-foreground">Add Slot</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default ManageSchedule;
