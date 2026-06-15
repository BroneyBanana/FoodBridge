const users = [
    {
        initials: "RT",
        name: "Rafi Tan",
        role: "Receiver",
        score: 12,
        reason: "Unverified profile and no-shows",
        status: "Suspend Soon"
    },
    {
        initials: "AM",
        name: "Amanda Miles",
        role: "Receiver",
        score: 18,
        reason: "3 missed pickups",
        status: "Review"
    },
    {
        initials: "JL",
        name: "Jason Lee",
        role: "Receiver",
        score: 24,
        reason: "Late cancellation reports",
        status: "Warning Sent"
    },
    {
        initials: "NS",
        name: "Nora Singh",
        role: "Donor",
        score: 27,
        reason: "Repeated food quality flags",
        status: "Monitor"
    },
    {
        initials: "CW",
        name: "Chloe Wong",
        role: "Receiver",
        score: 35,
        reason: "Incomplete pickup confirmations",
        status: "Monitor"
    },
    {
        initials: "DK",
        name: "Daniel Koh",
        role: "Donor",
        score: 42,
        reason: "Multiple late donation updates",
        status: "Warning Sent"
    },
    {
        initials: "FH",
        name: "Farah Hassan",
        role: "Receiver",
        score: 58,
        reason: "Occasional cancellation reports",
        status: "Stable"
    }
];

const slider = document.getElementById("slider");
const sliderValue = document.getElementById("sliderValue");
const riskCount = document.getElementById("riskCount");
const riskList = document.getElementById("riskList");

function getScoreClass(score) {
    return score <= 20 ? "danger" : "warning";
}

function renderUsersAtRisk() {
    const threshold = Number(slider.value);
    const usersAtRisk = users.filter((user) => user.score <= threshold);

    sliderValue.innerText = threshold;
    riskCount.innerText = `(${usersAtRisk.length})`;

    if (usersAtRisk.length === 0) {
        riskList.innerHTML = '<p class="empty-risk">No users currently at or below this threshold.</p>';
        return;
    }

    riskList.innerHTML = usersAtRisk.map((user) => `
        <div class="risk-card">
            <div class="risk-user">
                <div class="risk-avatar">${user.initials}</div>
                <div>
                    <h3>${user.name}</h3>
                    <p>${user.role}</p>
                </div>
            </div>
            <div class="risk-meta">
                <span class="score ${getScoreClass(user.score)}">${user.score}</span>
                <span>${user.reason}</span>
                <span class="status-chip">${user.status}</span>
            </div>
        </div>
    `).join("");
}

slider.addEventListener("input", renderUsersAtRisk);
renderUsersAtRisk();
