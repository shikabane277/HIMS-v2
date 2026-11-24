{{--
    How a competency gap is calculated. Shown on the employee gap analysis and
    the gap-analysis overview so HR, supervisors and staff can check any number
    by hand. Must stay in step with CompetencyGapAnalysisService::computeGaps().
--}}
<div class="hims-card mb-4">
    <div class="card-header" style="cursor:pointer" onclick="var b=this.nextElementSibling; b.style.display = b.style.display === 'none' ? '' : 'none'; this.querySelector('.chev').classList.toggle('bi-chevron-down'); this.querySelector('.chev').classList.toggle('bi-chevron-up');">
        <h5><i class="bi bi-calculator"></i> How gaps are calculated</h5>
        <i class="bi bi-chevron-down chev"></i>
    </div>
    <div class="card-body" style="display:none;font-size:13px;line-height:1.6">
        <div class="row g-3">
            <div class="col-lg-6">
                <p style="margin:0 0 8px"><strong>1. Proficiency scale (1–5)</strong></p>
                <table class="hims-table" style="font-size:12.5px">
                    <thead><tr><th>Level</th><th>Meaning</th></tr></thead>
                    <tbody>
                        <tr><td data-label="Level">1</td><td data-label="Meaning">Novice — needs direct supervision</td></tr>
                        <tr><td data-label="Level">2</td><td data-label="Meaning">Advanced beginner — performs with guidance</td></tr>
                        <tr><td data-label="Level">3</td><td data-label="Meaning">Competent — performs independently</td></tr>
                        <tr><td data-label="Level">4</td><td data-label="Meaning">Proficient — handles complex cases, guides others</td></tr>
                        <tr><td data-label="Level">5</td><td data-label="Meaning">Expert — sets practice, teaches and audits</td></tr>
                    </tbody>
                </table>
                <p style="margin:10px 0 0;color:#6b7280"><em>Required</em> = the higher of the job role's minimum level and the competency's hospital standard. Hospital-wide mandatory competencies apply to every role at their standard level. <em>Current</em> = the latest assessment recorded (synced from HR2).</p>
            </div>
            <div class="col-lg-6">
                <p style="margin:0 0 4px"><strong>2. Gap</strong></p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-family:monospace">Gap = Current level − Required level</div>
                <p style="margin:4px 0 12px;color:#6b7280">Example: current 2, required 4 → gap −2. Zero or positive means the requirement is met.</p>

                <p style="margin:0 0 4px"><strong>3. Severity</strong></p>
                <ul style="margin:0 0 12px;padding-left:18px">
                    <li><span class="hims-badge red">Critical</span> gap of −2 or worse, <em>or</em> −1 on a mandatory / critical competency, <em>or</em> not yet assessed on a mandatory / critical competency</li>
                    <li><span class="hims-badge yellow">Moderate</span> gap of −1 on any other competency</li>
                    <li><span class="hims-badge green">Met</span> gap of 0 or more</li>
                </ul>

                <p style="margin:0 0 4px"><strong>4. Priority (0–100)</strong></p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-family:monospace">Priority = |Gap| × 25 + 30 (if mandatory/critical) + 15 (if still below after training), max 100</div>
                <p style="margin:4px 0 12px;color:#6b7280">Unassessed: 90 if mandatory/critical, otherwise 60. Example: gap −2 on a mandatory skill, already trained → 50 + 30 + 15 = 95.</p>

                <p style="margin:0 0 4px"><strong>5. Overall figures</strong></p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-family:monospace">Readiness % = competencies met ÷ competencies required × 100<br>Coverage % = Σ min(current, required) ÷ Σ required × 100</div>
                <p style="margin:4px 0 0;color:#6b7280">Readiness counts only fully met competencies; coverage also credits partial progress (unassessed counts as 0).</p>
            </div>
        </div>
    </div>
</div>
