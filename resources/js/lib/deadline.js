/**
 * Mirrors App\Trips\DeadlineCalculator for display (F-003 AC-21–AC-23). Works
 * on wall-clock strings in the organization's zone; the server recomputes and
 * validates on save.
 */

const pad = (n) => String(n).padStart(2, '0');

function shiftDate(date, days) {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

/** Local "YYYY-MM-DD HH:mm", or null when the preset is unavailable or incomplete. */
export function computeDeadline(preset, startDate, meetingTime, customLocal = null) {
    if (!startDate) {
        return null;
    }

    switch (preset) {
        case 'h1':
        case 'h3':
        case 'h7':
            return `${shiftDate(startDate, -Number(preset.slice(1)))} 23:59`;
        case 'same_day': {
            if (!meetingTime) {
                return null;
            }

            const [hour, minute] = meetingTime.split(':').map(Number);
            const total = hour * 60 + minute - 60;
            const date = total < 0 ? shiftDate(startDate, -1) : startDate;
            const minutes = (total + 24 * 60) % (24 * 60);

            return `${date} ${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
        }
        case 'custom':
            return customLocal ? customLocal.replace('T', ' ').slice(0, 16) : null;
        default:
            return null;
    }
}

/** The meeting time on the start date, or 23:59 when there is none (AC-22). */
export function latestAllowed(startDate, meetingTime) {
    return `${startDate} ${meetingTime || '23:59'}`;
}

/** AC-22: "Sampai hari keberangkatan" needs a meeting time. */
export function availablePresets(presets, meetingTime) {
    return presets.filter((preset) => preset !== 'same_day' || Boolean(meetingTime));
}

/**
 * Options for the picker with their real date and whether they can be chosen
 * (past or after the meeting time are disabled, AC-23).
 */
export function presetOptions(presets, startDate, meetingTime, nowLocal) {
    return availablePresets(presets, meetingTime).map((preset) => {
        const deadline = preset === 'custom' ? null : computeDeadline(preset, startDate, meetingTime);

        return {
            value: preset,
            deadline,
            disabled: deadline !== null && (deadline <= nowLocal || deadline > latestAllowed(startDate, meetingTime)),
        };
    });
}

export function isDeadlineValid(deadline, startDate, meetingTime, nowLocal) {
    return deadline !== null && deadline > nowLocal && deadline <= latestAllowed(startDate, meetingTime);
}
