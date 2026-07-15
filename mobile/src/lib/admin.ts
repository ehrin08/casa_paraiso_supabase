export const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as const

export function serviceStateLabel(active: boolean): string { return active ? 'Active' : 'Inactive' }
export function therapistStateLabel(active: boolean, bookable: boolean): string { return !active ? 'Inactive' : bookable ? 'Bookable' : 'Not bookable' }
