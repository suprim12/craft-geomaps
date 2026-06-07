# Querying in GraphQL

The GraphQL query input supports all the same parameters as regular [Searching](/getting-started/usage#searching), with one exception: the `location` parameter only accepts a string value. To search by lat/lng coordinates, use the `coordinate` input instead.

```graphql
{
  entries(section: "locations",
  fieldHandle:{
    location: "Adelaide, South Australia",
    radius: 10
    coordinate:{
        lat: -34.928499,
        lng: 138.600746,
    },
  },
      orderBy: "distance") {
    ... on EntryInterface {
      title
      ... on contentPage_Entry {
      fieldHandle {
          lat
          lng
          address1
          full_address
          geoJson
          postcode
          suburb
          country
          countryCode
        }
      }
    }
  }
}
```

::: tip
The `distance` field in the response is only populated when you perform a location search using the `location` or `coordinate` inputs.
:::
