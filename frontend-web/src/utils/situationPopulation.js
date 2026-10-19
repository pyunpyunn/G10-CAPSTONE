const sum = values => !values.length || values.some(v => v == null) ? null : values.reduce((a,b) => a + Number(b),0)
const areaKey = row => JSON.stringify([row.sitio ?? null,row.purok ?? null])
export function situationPopulation(s) {
  if (s.population_tables) return {...s.population_tables, insideDemographics:s.population_tables.inside_demographics.map(([label,persons]) => [label,null,persons])}
  const areas = new Map((s.household?.puroks || []).map(p => [areaKey(p),{sitio:p.sitio,purok:p.purok,families:p.affected_families ?? p.affected,persons:p.affected_persons}]))
  const members = s.evacuation?.members || [], groups = new Map()
  for (const h of s.evacuation?.households || []) {
    const id = h.household_id ?? h.household
    if (id != null) groups.set(String(id),{...h,persons:h.evacuated_members,members:[]})
  }
  for (const m of members) {
    const id = m.household_id ?? m.household
    if (id == null) continue
    if (!groups.has(String(id))) groups.set(String(id),{...m,members:[]})
    groups.get(String(id)).members.push(m)
  }
  for (const h of groups.values()) {
    if (!areas.has(areaKey(h))) areas.set(areaKey(h),{sitio:h.sitio,purok:h.purok,families:null,persons:null})
    if (h.persons == null && h.members.length) h.persons = h.members.length
  }
  const displaced = {inside:[],outside:[],total:[]}
  for (const [key,area] of areas) {
    const families = [...groups.values()].filter(h => areaKey(h) === key)
    for (const location of Object.keys(displaced)) {
      let unknown = !families.length, count = 0
      const persons = []
      for (const h of families) {
        if (location === 'total') { count++; persons.push(h.persons ?? null); continue }
        if (h.members.length) {
          if (h.members.some(m => !['inside','outside'].includes(m.displacement_location))) unknown = true
          const selected = h.members.filter(m => m.displacement_location === location)
          if (selected.length) { count++; persons.push(selected.length) }
        } else if (!['inside','outside'].includes(h.displacement_location)) unknown = true
        else if (h.displacement_location === location) { count++; persons.push(h.persons ?? null) }
      }
      displaced[location].push({sitio:area.sitio,purok:area.purok,families:unknown ? null : count,persons:unknown ? null : persons.length ? sum(persons) : 0})
    }
  }
  return {affected:[...areas.values()],displaced,insideDemographics:insideDemographics(members.filter(m => m.displacement_location === 'inside'))}
}
export function insideDemographics(members) {
  const count = (field,predicate) => !members.length || members.some(m => m[field] == null) ? null : members.filter(m => predicate(m[field])).length
  return [
    ['Male',null,count('sex',v => ['male','m'].includes(v.toLowerCase()))],
    ['Female',null,count('sex',v => ['female','f'].includes(v.toLowerCase()))],
    ['Infants (under 1)',null,count('age',v => v < 1)],
    ['Children (1–17)',null,count('age',v => v >= 1 && v < 18)],
    ['Elderly (60+)',null,count('age',v => v >= 60)],
    ['PWD',null,count('is_pwd',Boolean)],['Pregnant',null,count('is_pregnant',Boolean)],['Lactating',null,count('is_lactating',Boolean)],
  ]
}
