import { useEffect, useState } from "react";
import { UserPlus, Search, Edit2, Trash2, X, Check } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";

interface UserData {
  id: number;
  student_id: string;
  full_name: string;
  email: string;
  role: string;
  status: string;
}

const API_BASE = "/campuscare-api/backend";

const roleMapForBackend = (role: string) => {
  if (role === "Counselor") return "Counsellor";
  return role;
};

const roleMapForDisplay = (role: string) => {
  if (role === "Counsellor") return "Counselor";
  return role;
};

const ManageUsers = () => {
  const [users, setUsers] = useState<UserData[]>([]);
  const [search, setSearch] = useState("");
  const [filterRole, setFilterRole] = useState("all");
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<UserData | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<number | null>(null);

  const [formData, setFormData] = useState({
    student_id: "",
    full_name: "",
    email: "",
    password: "",
    role: "Student",
    status: "Active",
  });

  const fetchUsers = async () => {
    try {
      const response = await fetch(`${API_BASE}/users/get_users.php`);
      const data = await response.json();

      if (data.success) {
        setUsers(data.users || []);
      } else {
        toast.error(data.message || "Failed to load users.");
      }
    } catch (error) {
      console.error("Fetch users error:", error);
      toast.error("Could not connect to the server.");
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  const filtered = users.filter(
    (u) =>
      (filterRole === "all" || roleMapForDisplay(u.role) === filterRole) &&
      (u.full_name.toLowerCase().includes(search.toLowerCase()) ||
        u.student_id.toLowerCase().includes(search.toLowerCase()) ||
        u.email.toLowerCase().includes(search.toLowerCase()))
  );

  const openAdd = () => {
    setEditingUser(null);
    setFormData({
      student_id: "",
      full_name: "",
      email: "",
      password: "",
      role: "Student",
      status: "Active",
    });
    setDialogOpen(true);
  };

  const openEdit = (user: UserData) => {
    setEditingUser(user);
    setFormData({
      student_id: user.student_id,
      full_name: user.full_name,
      email: user.email,
      password: "",
      role: roleMapForDisplay(user.role),
      status: user.status,
    });
    setDialogOpen(true);
  };

  const handleSave = async () => {
    if (!formData.student_id.trim() || !formData.full_name.trim() || !formData.role.trim()) {
      toast.error("Please fill in all required fields");
      return;
    }

    try {
      if (editingUser) {
        const response = await fetch(`${API_BASE}/users/update_user.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            id: editingUser.id,
            full_name: formData.full_name,
            role: roleMapForBackend(formData.role),
            status: formData.status,
          }),
        });

        const data = await response.json();

        if (data.success) {
          toast.success(`${formData.full_name} updated successfully`);
          setDialogOpen(false);
          fetchUsers();
        } else {
          toast.error(data.message || "Failed to update user.");
        }
      } else {
        if (!formData.email.trim() || !formData.password.trim()) {
          toast.error("Email and password are required for new users");
          return;
        }

        const response = await fetch(`${API_BASE}/users/add_user.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            full_name: formData.full_name,
            student_id: formData.student_id,
            email: formData.email,
            password: formData.password,
            role: roleMapForBackend(formData.role),
            status: formData.status,
          }),
        });

        const data = await response.json();

        if (data.success) {
          toast.success(`${formData.full_name} added successfully`);
          setDialogOpen(false);
          fetchUsers();
        } else {
          toast.error(data.message || "Failed to add user.");
        }
      }
    } catch (error) {
      console.error("Save user error:", error);
      toast.error("Could not connect to the server.");
    }
  };

  const handleDelete = async (id: number) => {
    try {
      const user = users.find((u) => u.id === id);

      const response = await fetch(`${API_BASE}/users/delete_user.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success(`${user?.full_name} removed`);
        setDeleteConfirm(null);
        fetchUsers();
      } else {
        toast.error(data.message || "Failed to delete user.");
      }
    } catch (error) {
      console.error("Delete user error:", error);
      toast.error("Could not connect to the server.");
    }
  };

  const toggleStatus = async (user: UserData) => {
    const newStatus = user.status === "Active" ? "Inactive" : "Active";

    try {
      const response = await fetch(`${API_BASE}/users/update_user.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: user.id,
          full_name: user.full_name,
          role: user.role,
          status: newStatus,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success(`${user.full_name} set to ${newStatus}`);
        fetchUsers();
      } else {
        toast.error(data.message || "Failed to update status.");
      }
    } catch (error) {
      console.error("Toggle status error:", error);
      toast.error("Could not connect to the server.");
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Manage Users</h1>
          <p className="text-muted-foreground text-sm mt-1">Add, edit, or remove system users</p>
        </div>
        <Button className="gradient-primary text-primary-foreground rounded-xl gap-2" onClick={openAdd}>
          <UserPlus className="w-4 h-4" /> Add User
        </Button>
      </div>

      <div className="flex gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            placeholder="Search by name, ID, or email..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-10 h-11 rounded-xl"
          />
        </div>

        <Select value={filterRole} onValueChange={setFilterRole}>
          <SelectTrigger className="w-40 h-11 rounded-xl">
            <SelectValue placeholder="Filter role" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Roles</SelectItem>
            <SelectItem value="Student">Student</SelectItem>
            <SelectItem value="Counselor">Counselor</SelectItem>
            <SelectItem value="Facilitator">Facilitator</SelectItem>
            <SelectItem value="Instructor">Instructor</SelectItem>
            <SelectItem value="Administrator">Administrator</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="bg-card rounded-2xl shadow-card overflow-hidden">
        <div className="grid grid-cols-[1fr_2fr_1.5fr_1fr_1fr_auto] gap-4 px-5 py-3 bg-muted/50 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
          <span>ID</span>
          <span>Name</span>
          <span>Email</span>
          <span>Role</span>
          <span>Status</span>
          <span>Actions</span>
        </div>

        {filtered.map((user) => (
          <div
            key={user.id}
            className="grid grid-cols-[1fr_2fr_1.5fr_1fr_1fr_auto] gap-4 px-5 py-4 border-t border-border items-center hover:bg-muted/30 transition-colors"
          >
            <span className="text-sm font-mono text-muted-foreground">{user.student_id}</span>
            <span className="text-sm font-medium text-foreground">{user.full_name}</span>
            <span className="text-sm text-muted-foreground truncate">{user.email}</span>
            <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-secondary text-secondary-foreground w-fit">
              {roleMapForDisplay(user.role)}
            </span>

            <button
              onClick={() => toggleStatus(user)}
              className={`text-xs font-semibold px-2.5 py-1 rounded-full w-fit cursor-pointer transition-colors ${
                user.status === "Active"
                  ? "bg-accent/20 text-accent hover:bg-accent/30"
                  : "bg-muted text-muted-foreground hover:bg-muted/80"
              }`}
            >
              {user.status}
            </button>

            <div className="flex gap-2">
              <button onClick={() => openEdit(user)} className="p-2 rounded-lg hover:bg-muted transition-colors" title="Edit user">
                <Edit2 className="w-4 h-4 text-muted-foreground" />
              </button>

              {deleteConfirm === user.id ? (
                <div className="flex gap-1">
                  <button
                    onClick={() => handleDelete(user.id)}
                    className="p-2 rounded-lg bg-destructive/10 hover:bg-destructive/20 transition-colors"
                    title="Confirm delete"
                  >
                    <Check className="w-4 h-4 text-destructive" />
                  </button>
                  <button
                    onClick={() => setDeleteConfirm(null)}
                    className="p-2 rounded-lg hover:bg-muted transition-colors"
                    title="Cancel"
                  >
                    <X className="w-4 h-4 text-muted-foreground" />
                  </button>
                </div>
              ) : (
                <button
                  onClick={() => setDeleteConfirm(user.id)}
                  className="p-2 rounded-lg hover:bg-destructive/10 transition-colors"
                  title="Delete user"
                >
                  <Trash2 className="w-4 h-4 text-destructive" />
                </button>
              )}
            </div>
          </div>
        ))}

        {filtered.length === 0 && (
          <div className="text-center py-8 text-muted-foreground text-sm">No users found.</div>
        )}
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="rounded-2xl">
          <DialogHeader>
            <DialogTitle>{editingUser ? "Edit User" : "Add New User"}</DialogTitle>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>User ID</Label>
              <Input
                value={formData.student_id}
                onChange={(e) => setFormData({ ...formData, student_id: e.target.value })}
                placeholder="e.g. STU-2024-006"
                className="rounded-xl"
                disabled={!!editingUser}
              />
            </div>

            <div className="space-y-2">
              <Label>Full Name</Label>
              <Input
                value={formData.full_name}
                onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
                placeholder="Enter full name"
                className="rounded-xl"
              />
            </div>

            {!editingUser && (
              <>
                <div className="space-y-2">
                  <Label>Email</Label>
                  <Input
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder="Enter email"
                    className="rounded-xl"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Password</Label>
                  <Input
                    type="password"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    placeholder="Enter password"
                    className="rounded-xl"
                  />
                </div>
              </>
            )}

            <div className="space-y-2">
              <Label>Role</Label>
              <Select value={formData.role} onValueChange={(v) => setFormData({ ...formData, role: v })}>
                <SelectTrigger className="rounded-xl">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Student">Student</SelectItem>
                  <SelectItem value="Counselor">Counselor</SelectItem>
                  <SelectItem value="Facilitator">Facilitator</SelectItem>
                  <SelectItem value="Instructor">Instructor</SelectItem>
                  <SelectItem value="Administrator">Administrator</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>Status</Label>
              <Select value={formData.status} onValueChange={(v) => setFormData({ ...formData, status: v })}>
                <SelectTrigger className="rounded-xl">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Active">Active</SelectItem>
                  <SelectItem value="Inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} className="rounded-xl">
              Cancel
            </Button>
            <Button onClick={handleSave} className="rounded-xl gradient-primary text-primary-foreground">
              {editingUser ? "Save Changes" : "Add User"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default ManageUsers;

