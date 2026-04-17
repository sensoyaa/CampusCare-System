import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Brain, CheckCircle2, ArrowRight, ArrowLeft, RotateCcw } from "lucide-react";
import { Progress } from "@/components/ui/progress";
import { toast } from "sonner";

const API_BASE = "/campuscare-api/backend";

const questions = [
  {
    q: "Over the past 2 weeks, how often have you felt down, depressed, or hopeless?",
    options: ["Not at all", "Several days", "More than half the days", "Nearly every day"],
  },
  {
    q: "How often have you had little interest or pleasure in doing things?",
    options: ["Not at all", "Several days", "More than half the days", "Nearly every day"],
  },
  {
    q: "How often have you felt nervous, anxious, or on edge?",
    options: ["Not at all", "Several days", "More than half the days", "Nearly every day"],
  },
  {
    q: "How often have you had trouble falling or staying asleep, or sleeping too much?",
    options: ["Not at all", "Several days", "More than half the days", "Nearly every day"],
  },
  {
    q: "How often have you felt tired or had little energy?",
    options: ["Not at all", "Several days", "More than half the days", "Nearly every day"],
  },
];

const MentalHealthTest = () => {
  const [answers, setAnswers] = useState<number[]>(Array(questions.length).fill(-1));
  const [currentQ, setCurrentQ] = useState(0);
  const [submitted, setSubmitted] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const setAnswer = (optIndex: number) => {
    const updated = [...answers];
    updated[currentQ] = optIndex;
    setAnswers(updated);
  };

  const score = answers.reduce((a, b) => a + (b >= 0 ? b : 0), 0);
  const answeredCount = answers.filter((a) => a >= 0).length;
  const progress = (answeredCount / questions.length) * 100;

  const getResult = () => {
    if (score <= 4) {
      return {
        level: "Low",
        message: "You seem to be doing well. Keep up with healthy habits and self-care routines.",
        color: "text-green-600",
        bg: "bg-green-50 border-green-200",
        emoji: "ðŸ˜Š",
      };
    }
    if (score <= 9) {
      return {
        level: "Moderate",
        message: "Consider talking to a counselor for additional support. It is okay to seek help.",
        color: "text-amber-600",
        bg: "bg-amber-50 border-amber-200",
        emoji: "ðŸ¤”",
      };
    }
    return {
      level: "High",
      message: "We recommend scheduling an appointment with the guidance office for support.",
      color: "text-red-600",
      bg: "bg-red-50 border-red-200",
      emoji: "ðŸ’™",
    };
  };

  const handleSubmit = async () => {
    const userId = localStorage.getItem("campuscare_user_id");

    if (!userId) {
      toast.error("User not found. Please log in again.");
      return;
    }

    if (!answers.every((a) => a >= 0)) {
      toast.error("Please answer all questions first.");
      return;
    }

    const result = getResult();

    try {
      setIsSaving(true);

      const response = await fetch(`${API_BASE}/assessments/add_test.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(userId),
          answer_1: questions[0].options[answers[0]],
          answer_2: questions[1].options[answers[1]],
          result_text: `Stress Level: ${result.level} | Score: ${score}/${questions.length * 3} | ${result.message}`,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success("Assessment submitted successfully.");
        setSubmitted(true);
      } else {
        toast.error(data.message || "Failed to save assessment.");
      }
    } catch (error) {
      console.error("Assessment error:", error);
      toast.error("Could not connect to the server.");
    } finally {
      setIsSaving(false);
    }
  };

  if (submitted) {
    const result = getResult();

    return (
      <div className="max-w-xl mx-auto animate-fade-in">
        <div className="bg-card rounded-2xl p-8 shadow-card text-center">
          <div className="text-5xl mb-4">{result.emoji}</div>

          <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-5">
            <CheckCircle2 className="w-8 h-8 text-primary" />
          </div>

          <h2 className="text-2xl font-bold text-foreground mb-4">Assessment Complete</h2>

          <div className={`rounded-xl p-4 border mb-6 ${result.bg}`}>
            <p className={`text-lg font-bold mb-1 ${result.color}`}>
              Stress Level: {result.level}
            </p>
            <p className="text-sm text-foreground/70">{result.message}</p>
          </div>

          <div className="bg-muted/50 rounded-xl p-4 mb-6">
            <p className="text-xs text-muted-foreground mb-2">Your Score</p>
            <p className="text-3xl font-bold text-foreground">
              {score}{" "}
              <span className="text-sm font-normal text-muted-foreground">
                / {questions.length * 3}
              </span>
            </p>
          </div>

          <div className="flex gap-3">
            <Button
              onClick={() => {
                setSubmitted(false);
                setAnswers(Array(questions.length).fill(-1));
                setCurrentQ(0);
              }}
              variant="outline"
              className="flex-1 rounded-xl h-11"
            >
              <RotateCcw className="w-4 h-4 mr-2" /> Retake
            </Button>

            <Button
              onClick={() => (window.location.href = "/book-appointment")}
              className="flex-1 rounded-xl h-11 gradient-primary"
            >
              Book Appointment
            </Button>
          </div>
        </div>
      </div>
    );
  }

  const q = questions[currentQ];

  return (
    <div className="max-w-xl mx-auto animate-fade-in">
      <div className="flex items-center gap-3 mb-2">
        <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
          <Brain className="w-5 h-5 text-primary" />
        </div>
        <div>
          <h1 className="text-xl font-bold text-foreground">Mental Health Check-in</h1>
          <p className="text-sm text-muted-foreground">Quick self-assessment</p>
        </div>
      </div>

      <div className="mt-6 mb-4">
        <div className="flex justify-between text-xs text-muted-foreground mb-2">
          <span>
            Question {currentQ + 1} of {questions.length}
          </span>
          <span>{answeredCount} answered</span>
        </div>
        <Progress value={progress} className="h-2" />
      </div>

      <div className="bg-card rounded-2xl p-6 shadow-card mb-4">
        <p className="font-semibold text-foreground text-lg mb-6">{q.q}</p>

        <div className="space-y-2.5">
          {q.options.map((opt, oi) => (
            <button
              key={oi}
              onClick={() => setAnswer(oi)}
              className={`w-full py-3.5 px-5 rounded-xl text-sm font-medium text-left transition-all border ${
                answers[currentQ] === oi
                  ? "bg-primary text-primary-foreground border-primary shadow-md"
                  : "bg-muted/30 text-foreground border-border/40 hover:border-primary/40 hover:bg-secondary"
              }`}
            >
              <span
                className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs mr-3 ${
                  answers[currentQ] === oi
                    ? "bg-primary-foreground/20 text-primary-foreground"
                    : "bg-muted text-muted-foreground"
                }`}
              >
                {String.fromCharCode(65 + oi)}
              </span>
              {opt}
            </button>
          ))}
        </div>
      </div>

      <div className="flex gap-3">
        <Button
          onClick={() => setCurrentQ(Math.max(0, currentQ - 1))}
          disabled={currentQ === 0}
          variant="outline"
          className="rounded-xl h-11"
        >
          <ArrowLeft className="w-4 h-4 mr-1" /> Back
        </Button>

        {currentQ < questions.length - 1 ? (
          <Button
            onClick={() => setCurrentQ(currentQ + 1)}
            disabled={answers[currentQ] < 0}
            className="flex-1 rounded-xl h-11 gradient-primary"
          >
            Next <ArrowRight className="w-4 h-4 ml-1" />
          </Button>
        ) : (
          <Button
            onClick={handleSubmit}
            disabled={!answers.every((a) => a >= 0) || isSaving}
            className="flex-1 rounded-xl h-11 gradient-primary"
          >
            {isSaving ? "Submitting..." : "Submit Assessment"}
          </Button>
        )}
      </div>

      <div className="flex justify-center gap-2 mt-6">
        {questions.map((_, i) => (
          <button
            key={i}
            onClick={() => setCurrentQ(i)}
            className={`w-2.5 h-2.5 rounded-full transition-all ${
              i === currentQ
                ? "bg-primary scale-125"
                : answers[i] >= 0
                ? "bg-primary/40"
                : "bg-border"
            }`}
          />
        ))}
      </div>
    </div>
  );
};

export default MentalHealthTest;

