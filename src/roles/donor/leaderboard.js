// `leaderboardRows` will be injected via PHP

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