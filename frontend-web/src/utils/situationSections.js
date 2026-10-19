export const reportSections = [
  ['I','Situation Overview'],
  ['II','Status of Affected Areas and Population'],
  ['IV','Damage Assessment'],
  ['V','Search, Rescue and Retrieval Operations'],
  ['VI','Status of Evacuation Centers'],
  ['VII','Resources Status'],
]
export const operatorInputs = {
  I:['overview_text','Situation findings'],
  IV:['damage_text','Recorded damage findings'],
  V:['response_text','Dispatch findings'],
  VII:['resources_text','Resource findings'],
}
export const operatorSectionFields = Object.fromEntries(Object.entries(operatorInputs).map(([key,field]) => [key,[field]]))
export const operatorInputFields = Object.values(operatorInputs)

export const reportSectionNumbers = { I:'I', II:'II', IV:'III', V:'IV', VI:'V', VII:'VI' }
