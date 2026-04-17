import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ArrowLeft, KeyRound } from "lucide-react";
import { toast } from "sonner";

const roles = ["Administrator", "Counselors", "Facilitator", "Student", "Instructor"];

const roleIdLabels: Record<string, string> = {
  Administrator: "Admin ID",
  Counselors: "Counselor ID",
  Facilitator: "Facilitator ID",
  Student: "Student ID",
  Instructor: "Instructor ID",
};

const roleEmailDomains: Record<string, string> = {
  Student: "@student.buksu.edu.ph",
  Administrator: "@buksu.edu.ph",
  Counselors: "@buksu.edu.ph",
  Facilitator: "@buksu.edu.ph",
  Instructor: "@buksu.edu.ph",
};

const roleEmailPlaceholders: Record<string, string> = {
  Student: "example@student.buksu.edu.ph",
  Administrator: "example@buksu.edu.ph",
  Counselors: "example@buksu.edu.ph",
  Facilitator: "example@buksu.edu.ph",
  Instructor: "example@buksu.edu.ph",
};

type View = "role-select" | "login" | "register" | "forgot-password";

const API_BASE = "/campuscare-api/backend";

const validateEmail = (email: string, role: string): boolean => {
  const domain = roleEmailDomains[role];
  if (!domain) return false;
  return email.toLowerCase().endsWith(domain.toLowerCase());
};

const mapRoleForBackend = (role: string): string => {
  if (role === "Counselors") return "Counsellor";
  return role;
};

const Login = () => {
  const navigate = useNavigate();

  const [view, setView] = useState<View>("role-select");
  const [selectedRole, setSelectedRole] = useState("");
  const [userId, setUserId] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [resetSent, setResetSent] = useState(false);
  const [emailError, setEmailError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const idLabel = roleIdLabels[selectedRole] || "ID";
  const requiredDomain = roleEmailDomains[selectedRole] || "";

  const checkEmail = (value: string) => {
    setEmail(value);
    if (value && !validateEmail(value, selectedRole)) {
      setEmailError(`Email must end with ${requiredDomain}`);
    } else {
      setEmailError("");
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!email || !validateEmail(email, selectedRole)) {
      toast.error(`Please use a valid ${requiredDomain} email address`);
      setEmailError(`Email must end with ${requiredDomain}`);
      return;
    }

    if (!userId || !password) {
      toast.error("Please fill in all fields");
      return;
    }

    try {
      setIsLoading(true);

      const response = await fetch(`${API_BASE}/auth/login.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          email,
          password,
        }),
      });

      const text = await response.text();
      let data;

      try {
        data = JSON.parse(text);
      } catch {
        console.error("Login raw response:", text);
        toast.error("Backend did not return valid JSON");
        return;
      }

      if (!data.success) {
        toast.error(data.message || "Login failed");
        return;
      }

      const backendRole = mapRoleForBackend(selectedRole);

      if (data.user.role !== backendRole) {
        toast.error(`This account is not registered as ${selectedRole}`);
        return;
      }

      if (data.user.student_id && data.user.student_id !== userId) {
        toast.error(`${idLabel} does not match this account`);
        return;
      }

      localStorage.setItem("campuscare_user", JSON.stringify(data.user));
      localStorage.setItem("campuscare_user_name", data.user.full_name);
      localStorage.setItem("campuscare_role", data.user.role);
      localStorage.setItem("campuscare_email", data.user.email);
      localStorage.setItem("campuscare_user_id", String(data.user.id));
      localStorage.setItem("campuscare_student_id", data.user.student_id || "");

      toast.success("Login successful");
      navigate("/dashboard");
    } catch (error) {
      console.error("Login fetch error:", error);
      toast.error("Could not connect to the server");
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!email || !validateEmail(email, selectedRole)) {
      toast.error(`Please use a valid ${requiredDomain} email address`);
      setEmailError(`Email must end with ${requiredDomain}`);
      return;
    }

    if (!name || !userId || !password) {
      toast.error("Please fill in all fields");
      return;
    }

    try {
      setIsLoading(true);

      const payload = {
        full_name: name,
        student_id: userId,
        email,
        password,
        role: mapRoleForBackend(selectedRole),
      };

      console.log("REGISTER URL:", `${API_BASE}/auth/register.php`);
      console.log("REGISTER PAYLOAD:", payload);

      const response = await fetch(`${API_BASE}/auth/register.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      console.log("REGISTER STATUS:", response.status);

      const text = await response.text();
      console.log("REGISTER RAW RESPONSE:", text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (jsonError) {
        console.error("JSON parse error:", jsonError);
        toast.error("Backend did not return valid JSON");
        return;
      }

      if (!data.success) {
        toast.error(data.message || "Registration failed");
        return;
      }

      toast.success("Registration successful. Please log in.");
      setPassword("");
      setView("login");
    } catch (error) {
      console.error("Register fetch error:", error);
      alert("Register error: " + String(error));
      toast.error("Could not connect to the server");
    } finally {
      setIsLoading(false);
    }
  };

  const handleForgotPassword = (e: React.FormEvent) => {
    e.preventDefault();

    if (!email || !validateEmail(email, selectedRole)) {
      toast.error(`Please use a valid ${requiredDomain} email address`);
      setEmailError(`Email must end with ${requiredDomain}`);
      return;
    }

    setResetSent(true);
  };

  const isLoginValid =
    !!email && validateEmail(email, selectedRole) && !!userId && !!password;

  const isRegisterValid =
    !!email && validateEmail(email, selectedRole) && !!name && !!userId && !!password;

  return (
    <div className="min-h-screen flex">
      <div className="hidden lg:flex lg:w-1/2 gradient-primary items-center justify-center relative overflow-hidden">
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-20 left-10 w-64 h-64 rounded-full bg-white/20 blur-3xl" />
          <div className="absolute bottom-20 right-10 w-80 h-80 rounded-full bg-white/10 blur-3xl" />
        </div>
        <div className="text-center z-10 px-12 animate-fade-in">
          <img
            src="/images/logo.png"
            alt="CampusCare"
            className="w-32 h-32 mx-auto mb-8 drop-shadow-xl"
          />
          <h1 className="text-4xl font-bold text-primary-foreground mb-4">CampusCare</h1>
          <p className="text-primary-foreground/80 text-lg max-w-sm">
            Your university mental health and wellness companion
          </p>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center px-6 py-12">
        <div className="w-full max-w-md animate-fade-in">
          <div className="lg:hidden flex items-center justify-center mb-8">
            <img src="/images/logo.png" alt="CampusCare" className="w-16 h-16 mr-3" />
            <span className="text-2xl font-bold text-primary">CampusCare</span>
          </div>

          {view === "role-select" && (
            <div className="space-y-6">
              <div className="text-center">
                <h2 className="text-3xl font-bold text-foreground mb-2">Welcome!</h2>
                <p className="text-muted-foreground">Select your role to continue</p>
              </div>
              <div className="space-y-3">
                {roles.map((role) => (
                  <button
                    key={role}
                    onClick={() => {
                      setSelectedRole(role);
                      setEmail("");
                      setUserId("");
                      setPassword("");
                      setName("");
                      setEmailError("");
                      setResetSent(false);
                      setView("login");
                    }}
                    className="w-full h-14 rounded-2xl text-base font-semibold gradient-primary text-primary-foreground hover:opacity-90 transition-all duration-200 hover:scale-[1.02] shadow-card"
                  >
                    {role}
                  </button>
                ))}
              </div>
            </div>
          )}

          {view === "login" && (
            <>
              <button
                onClick={() => setView("role-select")}
                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mb-6 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                Back to role selection
              </button>

              <div className="inline-block px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-xs font-semibold mb-4">
                {selectedRole}
              </div>

              <h2 className="text-3xl font-bold text-foreground mb-2">Welcome Back!</h2>
              <p className="text-muted-foreground mb-8">Sign in as {selectedRole}</p>

              <form onSubmit={handleSubmit} className="space-y-5">
                <div className="space-y-2">
                  <Label htmlFor="email">BukSU Email</Label>
                  <Input
                    id="email"
                    type="email"
                    placeholder={roleEmailPlaceholders[selectedRole]}
                    value={email}
                    onChange={(e) => checkEmail(e.target.value)}
                    className={`h-12 rounded-xl ${
                      emailError ? "border-destructive focus-visible:ring-destructive" : ""
                    }`}
                    required
                  />
                  {emailError && <p className="text-xs text-destructive">{emailError}</p>}
                  <p className="text-xs text-muted-foreground">Required: {requiredDomain}</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="userId">{idLabel}</Label>
                  <Input
                    id="userId"
                    placeholder={`Enter your ${idLabel}`}
                    value={userId}
                    onChange={(e) => setUserId(e.target.value)}
                    className="h-12 rounded-xl"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password">Password</Label>
                  <Input
                    id="password"
                    type="password"
                    placeholder="Enter your password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="h-12 rounded-xl"
                    required
                  />
                </div>

                <div className="text-right">
                  <button
                    type="button"
                    onClick={() => {
                      setResetSent(false);
                      setEmailError("");
                      setView("forgot-password");
                    }}
                    className="text-sm text-primary hover:underline"
                  >
                    Forgot password?
                  </button>
                </div>

                <Button
                  type="submit"
                  disabled={!isLoginValid || isLoading}
                  className="w-full h-12 rounded-xl text-base font-semibold gradient-primary hover:opacity-90 transition-opacity disabled:opacity-50"
                >
                  {isLoading ? "Logging in..." : "Log In"}
                </Button>
              </form>

              <p className="mt-6 text-center text-muted-foreground">
                New to CampusCare{" "}
                <button
                  onClick={() => {
                    setEmailError("");
                    setView("register");
                  }}
                  className="text-primary font-semibold hover:underline"
                >
                  Register here
                </button>
              </p>
            </>
          )}

          {view === "register" && (
            <>
              <button
                onClick={() => setView("login")}
                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mb-6 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                Back to login
              </button>

              <div className="inline-block px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-xs font-semibold mb-4">
                {selectedRole}
              </div>

              <h2 className="text-3xl font-bold text-foreground mb-2">Create Account</h2>
              <p className="text-muted-foreground mb-8">Join CampusCare as {selectedRole}</p>

              <form onSubmit={handleRegister} className="space-y-5">
                <div className="space-y-2">
                  <Label htmlFor="name">Full Name</Label>
                  <Input
                    id="name"
                    placeholder="Enter your full name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className="h-12 rounded-xl"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">BukSU Email</Label>
                  <Input
                    id="email"
                    type="email"
                    placeholder={roleEmailPlaceholders[selectedRole]}
                    value={email}
                    onChange={(e) => checkEmail(e.target.value)}
                    className={`h-12 rounded-xl ${
                      emailError ? "border-destructive focus-visible:ring-destructive" : ""
                    }`}
                    required
                  />
                  {emailError && <p className="text-xs text-destructive">{emailError}</p>}
                  <p className="text-xs text-muted-foreground">Required: {requiredDomain}</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="userId">ID</Label>
                  <Input
                    id="userId"
                    placeholder="Enter your ID"
                    value={userId}
                    onChange={(e) => setUserId(e.target.value)}
                    className="h-12 rounded-xl"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password">Password</Label>
                  <Input
                    id="password"
                    type="password"
                    placeholder="Enter your password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="h-12 rounded-xl"
                    required
                  />
                </div>

                <Button
                  type="submit"
                  disabled={!isRegisterValid || isLoading}
                  className="w-full h-12 rounded-xl text-base font-semibold gradient-primary hover:opacity-90 transition-opacity disabled:opacity-50"
                >
                  {isLoading ? "Registering..." : "Register"}
                </Button>
              </form>

              <p className="mt-6 text-center text-muted-foreground">
                Already have an account{" "}
                <button
                  onClick={() => {
                    setEmailError("");
                    setView("login");
                  }}
                  className="text-primary font-semibold hover:underline"
                >
                  Log in
                </button>
              </p>
            </>
          )}

          {view === "forgot-password" && (
            <>
              <button
                onClick={() => setView("login")}
                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mb-6 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                Back to login
              </button>

              <div className="w-14 h-14 rounded-2xl gradient-primary flex items-center justify-center mb-6">
                <KeyRound className="w-7 h-7 text-primary-foreground" />
              </div>

              <h2 className="text-3xl font-bold text-foreground mb-2">Reset Password</h2>
              <p className="text-muted-foreground mb-8">
                Enter your BukSU email and we'll send you a reset link.
              </p>

              {resetSent ? (
                <div className="bg-secondary rounded-2xl p-6 text-center space-y-3">
                  <p className="font-semibold text-secondary-foreground">Reset link sent</p>
                  <p className="text-sm text-muted-foreground">
                    Check your email inbox for the password reset link.
                  </p>
                  <Button
                    onClick={() => setView("login")}
                    variant="outline"
                    className="mt-4 rounded-xl"
                  >
                    Back to Login
                  </Button>
                </div>
              ) : (
                <form onSubmit={handleForgotPassword} className="space-y-5">
                  <div className="space-y-2">
                    <Label htmlFor="resetEmail">BukSU Email</Label>
                    <Input
                      id="resetEmail"
                      type="email"
                      placeholder={roleEmailPlaceholders[selectedRole]}
                      value={email}
                      onChange={(e) => checkEmail(e.target.value)}
                      className={`h-12 rounded-xl ${
                        emailError ? "border-destructive focus-visible:ring-destructive" : ""
                      }`}
                      required
                    />
                    {emailError && <p className="text-xs text-destructive">{emailError}</p>}
                    <p className="text-xs text-muted-foreground">Required: {requiredDomain}</p>
                  </div>

                  <Button
                    type="submit"
                    disabled={!email || !validateEmail(email, selectedRole)}
                    className="w-full h-12 rounded-xl text-base font-semibold gradient-primary hover:opacity-90 transition-opacity disabled:opacity-50"
                  >
                    Send Reset Link
                  </Button>
                </form>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default Login;

