const users = [
    {
        initials: "RT",
        name: "Rafi Tan",
        role: "Receiver",
        score: 12
    },
    {
        initials: "AM",
        name: "Amanda Miles",
        role: "Receiver",
        score: 18
    },
    {
        initials: "JL",
        name: "Jason Lee",
        role: "Receiver",
        score: 24
    },
    {
        initials: "NS",
        name: "Nora Singh",
        role: "Donor",
        score: 27
    }
];

const slider = document.getElementById("slider");
const sliderValue = document.getElementById("sliderValue");
const riskCount = document.getElementById("riskCount");
const riskList = document.getElementById("riskList");
const atRiskRatingLimit = 30;

function getScoreClass(score) {
    return score <= 20 ? "danger" : "warning";
}

function renderUsersAtRisk() {
    const threshold = Number(slider.value);
    const usersAtRisk = users.filter((user) => user.score < atRiskRatingLimit);

    sliderValue.innerText = threshold;
    riskCount.innerText = `(${usersAtRisk.length})`;

    if (usersAtRisk.length === 0) {
        riskList.innerHTML = '<p class="empty-risk">No users currently below this threshold.</p>';
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
            </div>
        </div>
    `).join("");
}

slider.addEventListener("input", renderUsersAtRisk);
renderUsersAtRisk();
