# Usage

## Creating a Map Field

You can create a Map field the same way you would create any other field in Craft. Go to **Settings → Fields** and click the **New Field** button. Fill out the required fields and select **Geo Maps** from the **Field Type** dropdown.

You can then configure the initial state of the map and how it appears to the user. Use the map to select the initial location, and use the fields below to customise the layout.

## Displaying the Map

When accessing the Map field in Twig, you have access to the following properties:

| Property | Description |
|---|---|
| `lat` | The latitude of the selected map location |
| `lng` | The longitude of the selected map location |
| `zoom` | The zoom level of the map |
| `address` | The full address (see [Address](#address)) |
| `parts` | The separate parts of the address (see [Parts](#parts)) |
| `distance` | Distance from your search (only populated when [Searching](#searching)) |

### Address

As a string, it outputs the full address as it appears in the Map field:

```twig
{{ myMapField.full_address }}
```


The example above outputs the address as a comma-separated string, excluding the country.

| Part | Description |
|---|---|
| `address1` | The street address (not the full address) |
| `full_address` | The formatted address |
| `suburb` | The Suburb |
| `city` | The city of the location |
| `postcode` | The postal or zip code |
| `county` | The county |
| `state` | The state or region |
| `country` | The country |
| `countryCode` | The country Code |
| `geoJson` | The geo Json value |

