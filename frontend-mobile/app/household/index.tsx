import { StyleSheet, Text, View } from 'react-native';

export default function HouseholdHomeScreen() {
  return (
    <View style={styles.screen}>
      <Text style={styles.kicker}>Household Mobile</Text>
      <Text style={styles.title}>Status reporting will be built here.</Text>
      <Text style={styles.copy}>
        This role will submit household safety status, device battery, and location when the
        Household Status module is developed.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#f5f7fb',
  },
  kicker: {
    color: '#287c77',
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 8,
    color: '#172033',
    fontSize: 24,
    fontWeight: '900',
  },
  copy: {
    marginTop: 10,
    color: '#64748b',
    fontSize: 14,
    lineHeight: 20,
  },
});
