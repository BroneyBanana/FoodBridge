const leaderboardRows = [
  { rank: 4, initials: "C1", name: "Cafe 1920", trust: 78, TotalFoodDonated: 900 },
  { rank: 5, initials: "SS", name: "SuperMart Subang", trust: 65, TotalFoodDonated: 640 },
  { rank: 6, initials: "HB", name: "Healthy Bowls", trust: 88, TotalFoodDonated: 560 },
  { rank: 7, initials: "BE", name: "Bento Express", trust: 95, TotalFoodDonated: 500 },
  { rank: 8, initials: "FN", name: "Fruit Ninja", trust: 70, TotalFoodDonated: 360 }
];

const rankList = document.getElementById("rankList");

function createRankCard(donor) {
  return `
    <article class="rank-card">
      <div class="rank-number">#${donor.rank}</div>
      <div class="rank-avatar">${donor.initials}</div>

      <div class="rank-info">
        <h3>${donor.name}</h3>
        <div class="trust-row">
          <span>Trust Score: ${donor.trust}</span>
          <div class="trust-meter" style="--trust-width: ${donor.trust}%">
            <span></span>
          </div>
        </div>
      </div>

      <div class="rank-score">
        <strong>${donor.TotalFoodDonated}</strong> <span>Total Food Donated</span>
      </div>
    </article>
  `;
}

rankList.innerHTML = leaderboardRows.map(createRankCard).join("");